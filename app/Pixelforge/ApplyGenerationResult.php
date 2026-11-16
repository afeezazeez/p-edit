<?php

namespace App\Pixelforge;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ApplyGenerationResult
{
    public function __construct(private readonly PriceCalculator $calculator) {}

    public function apply(array $providerGeneration): Generation
    {
        return DB::transaction(function () use ($providerGeneration): Generation {
            $generation = $this->findLocalGeneration($providerGeneration);
            $generation = Generation::query()->lockForUpdate()->findOrFail($generation->id);
            if ($generation->status->isTerminal()) {
                return $generation;
            }

            return $this->applyStatus($generation, $providerGeneration);
        });
    }

    private function findLocalGeneration(array $provider): Generation
    {
        $localId = data_get($provider, 'metadata.local_generation_id');
        $query = Generation::query();
        $generation = $localId ? $query->find($localId) : null;
        $generation ??= Generation::query()->where('pixelforge_generation_id', $provider['id'])->first();

        return $generation ?? throw new RuntimeException('No local generation matches this provider result.');
    }

    private function applyStatus(Generation $generation, array $provider): Generation
    {
        $generation->pixelforge_generation_id = $provider['id'];
        $generation->rates = $provider['rates'] ?? $generation->rates;
        $status = GenerationStatus::from($provider['status']);
        if ($status === GenerationStatus::Queued && $generation->status === GenerationStatus::Processing) {
            return $generation;
        }

        if ($status === GenerationStatus::Succeeded) {
            $this->applySuccess($generation, $provider);
        } elseif ($status === GenerationStatus::Failed) {
            $this->applyFailure($generation, $provider);
        } else {
            $this->applyPendingStatus($generation, $provider, $status);
        }

        $generation->save();

        return $generation;
    }

    private function applySuccess(Generation $generation, array $provider): void
    {
        $usage = $provider['usage'];
        $generation->status = GenerationStatus::Succeeded;
        $generation->actual_usage = $usage;
        $generation->provider_cost = $this->calculator->providerCost($usage, $generation->rates);
        $generation->final_cost = $this->calculator->actualCost($usage, $generation->rates);
        $generation->image_url = $provider['image_url'];
        $generation->image_url_expires_at = $provider['image_url_expires_at'];
        $generation->started_at = $provider['started_at'] ?? $generation->started_at;
        $generation->completed_at = $provider['completed_at'];
        $generation->error_code = null;
        $generation->error_message = null;
    }

    private function applyFailure(Generation $generation, array $provider): void
    {
        $generation->status = GenerationStatus::Failed;
        $generation->provider_cost = '0.00000000000';
        $generation->final_cost = '0.00000000000';
        $generation->error_code = data_get($provider, 'error.code');
        $generation->error_message = data_get($provider, 'error.message');
        $generation->started_at = $provider['started_at'] ?? $generation->started_at;
        $generation->completed_at = $provider['completed_at'];
    }

    private function applyPendingStatus(Generation $generation, array $provider, GenerationStatus $status): void
    {
        $generation->status = $status;
        $generation->started_at = $provider['started_at'] ?? $generation->started_at;
    }
}
