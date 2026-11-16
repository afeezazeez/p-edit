<?php

namespace App\Jobs;

use App\Models\WebhookEvent;
use App\Pixelforge\ApplyGenerationResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPixelforgeWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [10, 30, 60, 120];

    public function __construct(public readonly int $eventId) {}

    public function handle(ApplyGenerationResult $apply): void
    {
        $event = WebhookEvent::query()->findOrFail($this->eventId);
        if ($event->event_type === 'ping') {
            $event->update(['processed_at' => now()]);

            return;
        }

        $apply->apply(data_get($event->payload, 'data.object'));
        $event->update(['processed_at' => now()]);
    }
}
