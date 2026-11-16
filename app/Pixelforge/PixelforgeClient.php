<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    public function pricing(): array
    {
        return $this->get('/pricing', authenticated: false);
    }

    public function createGeneration(array $payload, string $idempotencyKey): array
    {
        try {
            $response = $this->request()->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])->post('/generations', $payload);
        } catch (ConnectionException $exception) {
            throw new PixelforgeException(
                'Pixelforge did not confirm whether it accepted the generation.', true, previous: $exception,
            );
        }

        return $this->decode(
            $response->status(), $response->json(),
            accepted: $response->successful(), retryAfter: $response->header('Retry-After'),
        );
    }

    public function generation(string $providerId): array
    {
        return $this->get('/generations/'.rawurlencode($providerId));
    }

    public function usage(string $startDate, string $endDate): array
    {
        return $this->get('/usage?start_date='.$startDate.'&end_date='.$endDate);
    }

    private function get(string $path, bool $authenticated = true): array
    {
        try {
            $response = $this->request($authenticated)->get($path);
        } catch (ConnectionException $exception) {
            throw new PixelforgeException('Pixelforge could not be reached.', previous: $exception);
        }

        return $this->decode($response->status(), $response->json(), retryAfter: $response->header('Retry-After'));
    }

    private function request(bool $authenticated = true): PendingRequest
    {
        $request = Http::acceptJson()
            ->asJson()
            ->timeout(config('services.pixelforge.timeout'))
            ->baseUrl(rtrim(config('services.pixelforge.base_url'), '/'));

        return $authenticated ? $request->withToken(config('services.pixelforge.api_key')) : $request;
    }

    private function decode(int $status, mixed $body, bool $accepted = false, ?string $retryAfter = null): array
    {
        if ($status < 200 || $status >= 300 || ! is_array($body)) {
            $message = data_get($body, 'error.message', 'Pixelforge returned an unexpected response.');
            throw new PixelforgeException($message, $accepted, $status, is_numeric($retryAfter) ? (int) $retryAfter : null);
        }

        return $body;
    }
}
