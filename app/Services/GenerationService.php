<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use App\Pixelforge\PriceCalculator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GenerationService
{
    public function __construct(
        private readonly PixelforgeClient $client,
        private readonly PriceCalculator $calculator,
        private readonly ApplyGenerationResult $applyResult,
    ) {}

    public function history(User $user): LengthAwarePaginator
    {
        return $user->generations()->latest()->paginate(20);
    }

    public function estimate(array $input): array
    {
        $pricing = $this->client->pricing();

        return $this->calculator->estimate(
            $input['prompt'], count($input['reference_images'] ?? []),
            $input['size'], $input['quality'], $pricing,
        );
    }

    public function create(User $user, array $input, string $idempotencyKey): Generation
    {
        $generation = $this->findOrCreate($user, $input, $idempotencyKey);
        if ($generation->pixelforge_generation_id !== null) {
            return $generation;
        }

        return $this->submit($generation, $input, $idempotencyKey);
    }

    private function findOrCreate(User $user, array $input, string $key): Generation
    {
        $existing = Generation::query()->where('idempotency_key', $key)->first();
        if ($existing !== null) {
            return $this->assertSameRequest($existing, $user, $input);
        }

        $pricing = $this->client->pricing();
        $estimate = $this->calculator->estimate(
            $input['prompt'], count($input['reference_images'] ?? []),
            $input['size'], $input['quality'], $pricing,
        );

        try {
            return DB::transaction(fn () => Generation::query()->create([
                'user_id' => $user->id,
                'prompt' => $input['prompt'],
                'size' => $input['size'],
                'quality' => $input['quality'],
                'reference_images' => $input['reference_images'] ?? [],
                'status' => GenerationStatus::Pending,
                'idempotency_key' => $key,
                'estimated_cost' => $estimate['estimated_cost'],
                'rates' => $estimate['rates'],
            ]));
        } catch (QueryException $exception) {
            $existing = Generation::query()->where('idempotency_key', $key)->first();
            if ($existing === null) {
                throw $exception;
            }

            return $this->assertSameRequest($existing, $user, $input);
        }
    }

    private function assertSameRequest(Generation $generation, User $user, array $input): Generation
    {
        if ($generation->user_id !== $user->id || $generation->prompt !== $input['prompt']
            || $generation->size !== $input['size'] || $generation->quality !== $input['quality']
            || $generation->reference_images !== ($input['reference_images'] ?? [])) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This request key has already been used for different generation details.',
            ]);
        }

        return $generation;
    }

    private function submit(Generation $generation, array $input, string $key): Generation
    {
        try {
            $provider = $this->client->createGeneration($this->providerPayload($generation, $input), $key);
        } catch (PixelforgeException $exception) {
            $this->removeRejectedGeneration($generation, $exception);
            throw $exception;
        }

        return $this->recordAcceptedGeneration($generation, $provider);
    }

    private function providerPayload(Generation $generation, array $input): array
    {
        return [
            'prompt' => $input['prompt'],
            'size' => $input['size'],
            'quality' => $input['quality'],
            'reference_images' => $input['reference_images'] ?? [],
            'metadata' => ['local_generation_id' => $generation->id],
        ];
    }

    private function removeRejectedGeneration(Generation $generation, PixelforgeException $exception): void
    {
        if (! $exception->requestWasAccepted) {
            $generation->delete();
        }
    }

    private function recordAcceptedGeneration(Generation $generation, array $provider): Generation
    {
        DB::transaction(function () use ($generation, $provider): void {
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);
            $generation->pixelforge_generation_id = $provider['id'];
            $generation->rates = $provider['rates'];
            $generation->save();
        });

        return $this->applyResult->apply($provider);
    }
}
