<?php

namespace App\Jobs;

use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use App\Services\ApplyGenerationResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public function __construct(public readonly string $generationId) {}

    public function handle(PixelforgeClient $client, ApplyGenerationResult $apply): void
    {
        $generation = Generation::query()->findOrFail($this->generationId);
        if ($generation->status->isTerminal() || $generation->pixelforge_generation_id === null) {
            return;
        }

        try {
            $provider = $client->generation($generation->pixelforge_generation_id);
        } catch (PixelforgeException $exception) {
            $this->release($exception->retryAfter ?? 10);

            return;
        }
        $apply->apply($provider);
    }
}
