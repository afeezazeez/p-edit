<?php

namespace App\Pixelforge;

use App\Jobs\ProcessPixelforgeWebhook;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use JsonException;

class PixelforgeWebhookService
{
    private const EVENT_TYPES = [
        'generation.processing', 'generation.succeeded', 'generation.failed', 'ping',
    ];

    public function receive(string $body, string $signature, ?string $eventIdHeader): void
    {
        $this->verifySignature($body, $signature);
        $event = $this->decodeEvent($body);
        $this->validateEvent($event, $eventIdHeader);
        $this->storeAndQueue($event);
    }

    private function verifySignature(string $body, string $signature): void
    {
        $parts = $this->signatureParts($signature);
        $timestamp = filter_var($parts['t'] ?? null, FILTER_VALIDATE_INT);
        $secret = (string) config('services.pixelforge.webhook_secret');
        if ($timestamp === false || $secret === '') {
            throw new WebhookRejectedException('Invalid Pixelforge signature.');
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);
        if (abs(now()->timestamp - $timestamp) > 300 || ! hash_equals($expected, $parts['v1'] ?? '')) {
            throw new WebhookRejectedException('Invalid Pixelforge signature.');
        }
    }

    private function signatureParts(string $header): array
    {
        $parts = [];
        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
            $parts[$key] = $value;
        }

        return $parts;
    }

    private function decodeEvent(string $body): array
    {
        try {
            $event = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new WebhookRejectedException('Invalid Pixelforge event body.', $exception);
        }

        if (! is_array($event)) {
            throw new WebhookRejectedException('Invalid Pixelforge event body.');
        }

        return $event;
    }

    private function validateEvent(array $event, ?string $eventIdHeader): void
    {
        if (! is_string($event['id'] ?? null) || ! is_string($event['type'] ?? null)
            || ! in_array($event['type'], self::EVENT_TYPES, true)
            || ($eventIdHeader !== null && $eventIdHeader !== $event['id'])) {
            throw new WebhookRejectedException('Invalid Pixelforge event.');
        }
    }

    private function storeAndQueue(array $event): void
    {
        try {
            $record = WebhookEvent::query()->firstOrCreate(
                ['provider_event_id' => $event['id']],
                ['event_type' => $event['type'], 'payload' => $event],
            );
        } catch (QueryException $exception) {
            Log::warning('Webhook event persistence failed.', ['event_id' => $event['id']]);
            throw $exception;
        }

        if ($record->wasRecentlyCreated || $record->processed_at === null) {
            ProcessPixelforgeWebhook::dispatch($record->id)->afterCommit();
        }
    }
}
