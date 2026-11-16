<?php

use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

uses(TestCase::class);

it('sends all provider requests to the documented endpoints with the right authentication', function () {
    config([
        'services.pixelforge.base_url' => 'https://pixelforge.test/v1',
        'services.pixelforge.api_key' => 'pf_test_key',
        'services.pixelforge.timeout' => 10,
    ]);
    Http::fakeSequence()
        ->push(['rates' => ['input_text_tokens' => '5.00']], 200)
        ->push(['id' => 'gen_client', 'status' => 'queued', 'rates' => []], 202)
        ->push(['id' => 'gen_client', 'status' => 'succeeded'], 200)
        ->push(['total' => ['cost_usd' => '0.000010000']], 200);
    $client = app(PixelforgeClient::class);

    $client->pricing();
    $client->createGeneration(['prompt' => 'A lighthouse'], 'local-key');
    $client->generation('gen_client');
    $client->usage('2026-11-16', '2026-11-16');

    Http::assertSentCount(4);
    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/pricing'
        && $request->method() === 'GET' && ! $request->hasHeader('Authorization'));
    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/generations'
        && $request->method() === 'POST'
        && $request->hasHeader('Authorization', 'Bearer pf_test_key')
        && $request->hasHeader('Idempotency-Key', 'local-key'));
    Http::assertSent(fn ($request) => $request->url() === 'https://pixelforge.test/v1/generations/gen_client'
        && $request->method() === 'GET' && $request->hasHeader('Authorization', 'Bearer pf_test_key'));
    Http::assertSent(fn ($request) => str_contains($request->url(), '/usage?start_date=2026-11-16&end_date=2026-11-16')
        && $request->hasHeader('Authorization', 'Bearer pf_test_key'));
});

it('preserves provider rate-limit status and retry timing in its exception', function () {
    config(['services.pixelforge.base_url' => 'https://pixelforge.test/v1']);
    Http::fakeSequence()->push(
        ['error' => ['message' => 'Rate limited.']],
        429,
        ['Retry-After' => '7'],
    );

    try {
        app(PixelforgeClient::class)->pricing();
        test()->fail('Expected Pixelforge to reject the pricing request.');
    } catch (PixelforgeException $exception) {
        expect($exception->statusCode)->toBe(429)
            ->and($exception->retryAfter)->toBe(7)
            ->and($exception->getMessage())->toBe('Rate limited.');
    }
});
