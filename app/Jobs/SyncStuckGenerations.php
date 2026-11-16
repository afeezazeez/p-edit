<?php

namespace App\Jobs;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncStuckGenerations implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Generation::query()
            ->whereIn('status', [GenerationStatus::Queued->value, GenerationStatus::Processing->value])
            ->whereNotNull('pixelforge_generation_id')
            ->where('updated_at', '<=', now()->subMinute())
            ->orderBy('updated_at')
            ->limit(100)
            ->pluck('id')
            ->each(fn (string $id) => SyncGeneration::dispatch($id));
    }
}
