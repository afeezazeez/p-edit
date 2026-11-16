<?php

use App\Enums\GenerationStatus;
use App\Jobs\ProcessPixelforgeWebhook;
use App\Jobs\SyncGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Pixelforge\ApplyGenerationResult;
use App\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function pricingResponse(): array
{
    return [
        'rates' => [
            'input_text_tokens' => '5.00', 'input_image_tokens' => '10.00',
            'output_tokens' => '40.00', 'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4, 'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => ['low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450]],
            'reasoning' => ['low' => ['min' => 0, 'max' => 500], 'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000]],
        ],
    ];
}

function providerGeneration(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'gen_123', 'status' => 'queued', 'metadata' => ['local_generation_id' => ''],
        'rates' => pricingResponse()['rates'], 'usage' => null, 'image_url' => null,
        'image_url_expires_at' => null, 'error' => null, 'started_at' => null,
        'completed_at' => null,
    ], $overrides);
}

it('estimates the user price from live provider pricing', function () {
    Http::fake(['*pricing' => Http::response(pricingResponse())]);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/generations/estimate', [
        'prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low',
    ])->assertOk()->assertJsonPath('data.estimated_cost', '0.02183750000');
});

it('creates one queued provider generation using the selected inputs and idempotency key', function () {
    Http::fakeSequence()
        ->push(pricingResponse(), 200)
        ->push(providerGeneration(['status' => 'queued']), 202);
    $user = User::factory()->create();
    $key = 'fbd88391-79ec-4208-a762-82fa69c7bc72';

    $response = $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson('/generations', [
        'prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low',
        'reference_images' => ['https://example.test/style.png'],
    ]);

    $response->assertStatus(202)->assertJsonPath('data.status', 'queued');
    $this->assertDatabaseHas('generations', [
        'user_id' => $user->id, 'idempotency_key' => $key,
        'pixelforge_generation_id' => 'gen_123', 'status' => 'queued',
    ]);
    Http::assertSent(fn ($request) => $request->hasHeader('Idempotency-Key', $key)
        && data_get($request->data(), 'metadata.local_generation_id') !== null
        && data_get($request->data(), 'reference_images.0') === 'https://example.test/style.png');
});

it('reuses a successful local request when the same idempotency key is submitted again', function () {
    Http::fakeSequence()
        ->push(pricingResponse(), 200)
        ->push(providerGeneration(['status' => 'queued']), 202);
    $user = User::factory()->create();
    $input = ['prompt' => 'Same request', 'size' => '1024x1024', 'quality' => 'low'];
    $key = '7ea7ca99-42dc-4c96-a6fa-283399614925';

    $first = $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson('/generations', $input);
    $second = $this->withHeader('Idempotency-Key', $key)->postJson('/generations', $input);

    $first->assertAccepted();
    $second->assertAccepted()->assertJsonPath('data.id', $first->json('data.id'));
    $this->assertDatabaseCount('generations', 1);
    Http::assertSentCount(2);
});

it('rejects a reused idempotency key when request details differ', function () {
    Http::fakeSequence()
        ->push(pricingResponse(), 200)
        ->push(providerGeneration(['status' => 'queued']), 202);
    $user = User::factory()->create();
    $key = 'afe888c6-e372-40db-a2e9-16289af42a94';
    $input = ['prompt' => 'First prompt', 'size' => '1024x1024', 'quality' => 'low'];

    $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson('/generations', $input)
        ->assertAccepted();
    $this->withHeader('Idempotency-Key', $key)->postJson('/generations', [
        ...$input, 'prompt' => 'Different prompt',
    ])->assertUnprocessable();

    $this->assertDatabaseCount('generations', 1);
    Http::assertSentCount(2);
});

it('keeps an uncertain request and retries it with the same provider key', function () {
    $generationAttempts = 0;
    Http::fake(function ($request) use (&$generationAttempts) {
        if (str_ends_with($request->url(), '/pricing')) {
            return Http::response(pricingResponse());
        }
        $generationAttempts++;
        if ($generationAttempts === 1) {
            throw new ConnectionException('Connection lost after the request was sent.');
        }

        return Http::response(providerGeneration(['status' => 'queued']), 202);
    });
    $user = User::factory()->create();
    $key = '865f377b-c145-4f76-9fc9-733cecf82648';
    $input = ['prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low'];

    $this->actingAs($user)->withHeader('Idempotency-Key', $key)->postJson('/generations', $input)
        ->assertAccepted();
    $this->assertDatabaseHas('generations', ['idempotency_key' => $key, 'status' => 'pending']);
    $this->withHeader('Idempotency-Key', $key)->postJson('/generations', $input)
        ->assertAccepted()->assertJsonPath('data.status', 'queued');

    expect($generationAttempts)->toBe(2);
    $this->assertDatabaseCount('generations', 1);
});

it('returns the provider retry delay through centralized error handling', function () {
    Http::fakeSequence()
        ->push(pricingResponse(), 200)
        ->push(['error' => ['message' => 'Rate limited.']], 429, ['Retry-After' => '8']);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/generations', [
        'prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low',
    ])->assertStatus(429)->assertHeader('Retry-After', '8')->assertJsonStructure(['message']);

    $this->assertDatabaseCount('generations', 0);
});

it('rejects invalid generation settings without calling Pixelforge', function () {
    Http::preventStrayRequests();
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/generations', [
        'prompt' => '', 'size' => 'large', 'quality' => 'best',
        'reference_images' => ['not-a-url'],
    ])->assertUnprocessable();

    $this->withHeader('Idempotency-Key', 'not-a-uuid')->postJson('/generations', [
        'prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low',
    ])->assertUnprocessable();

    $this->assertDatabaseCount('generations', 0);
});

it('requires sign-in to access generation history', function () {
    $this->getJson('/generations')->assertUnauthorized();
});

it('requires sign-in to estimate or submit a generation', function () {
    Http::preventStrayRequests();
    $input = ['prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low'];

    $this->postJson('/generations/estimate', $input)->assertUnauthorized();
    $this->postJson('/generations', $input)->assertUnauthorized();

    $this->assertDatabaseCount('generations', 0);
});

it('shows a user only their own generation history', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $owned = generationFor($owner);
    generationFor($otherUser);

    $this->actingAs($owner)->getJson('/generations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $owned->id)
        ->assertJsonMissingPath('data.0.idempotency_key');
});

it('does not keep an unaccepted generation when the provider rejects it', function () {
    Http::fakeSequence()
        ->push(pricingResponse(), 200)
        ->push(['error' => ['message' => 'rejected']], 400);
    $user = User::factory()->create();

    $this->actingAs($user)->postJson('/generations', [
        'prompt' => '[blocked] prompt', 'size' => '1024x1024', 'quality' => 'low',
    ])->assertBadRequest();

    $this->assertDatabaseCount('generations', 0);
});

it('records a failed provider result with zero cost', function () {
    $user = User::factory()->create();
    $generation = generationFor($user, ['pixelforge_generation_id' => 'gen_failed']);

    app(ApplyGenerationResult::class)->apply(providerGeneration([
        'id' => 'gen_failed', 'status' => 'failed',
        'metadata' => ['local_generation_id' => $generation->id],
        'error' => ['code' => 'generation_failed', 'message' => 'The image could not be generated.'],
        'completed_at' => '2026-11-16T12:00:00Z',
    ]));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->provider_cost)->toBe('0.00000000000')
        ->and($generation->final_cost)->toBe('0.00000000000')
        ->and($generation->error_code)->toBe('generation_failed');
});

it('records a successful result once and ignores a late processing result', function () {
    $user = User::factory()->create();
    $generation = Generation::query()->create([
        'user_id' => $user->id, 'prompt' => 'A lighthouse', 'size' => '1024x1024',
        'quality' => 'low', 'reference_images' => [], 'idempotency_key' => (string) Str::uuid(),
        'estimated_cost' => '0.02183750000', 'rates' => pricingResponse()['rates'],
        'pixelforge_generation_id' => 'gen_123', 'status' => GenerationStatus::Queued,
    ]);
    $result = providerGeneration([
        'status' => 'succeeded', 'metadata' => ['local_generation_id' => $generation->id],
        'usage' => ['input_text_tokens' => 12, 'input_image_tokens' => 0, 'output_tokens' => 280,
            'reasoning_tokens' => 200, 'total_tokens' => 492],
        'image_url' => 'https://pixelforge.test/image.png',
        'image_url_expires_at' => '2026-11-17T12:00:00Z',
        'completed_at' => '2026-11-16T12:00:00Z',
    ]);
    $service = app(ApplyGenerationResult::class);

    $service->apply($result);
    $service->apply($result);
    $service->apply(providerGeneration([
        'status' => 'processing', 'metadata' => ['local_generation_id' => $generation->id],
    ]));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost)->toBe('0.01376000000')
        ->and($generation->final_cost)->toBe('0.01720000000')
        ->and($generation->image_url)->toBe('https://pixelforge.test/image.png');
});

it('uses the same result application when polling a pending generation', function () {
    $user = User::factory()->create();
    $generation = generationFor($user, ['pixelforge_generation_id' => 'gen_poll']);
    Http::fake(['*generations/gen_poll' => Http::response(providerGeneration([
        'id' => 'gen_poll', 'status' => 'failed',
        'metadata' => ['local_generation_id' => $generation->id],
        'error' => ['code' => 'generation_failed', 'message' => 'Unable to generate.'],
        'completed_at' => '2026-11-16T12:00:00Z',
    ]))]);

    (new SyncGeneration($generation->id))->handle(
        app(PixelforgeClient::class), app(ApplyGenerationResult::class),
    );

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->final_cost)->toBe('0.00000000000');
});

it('accepts a signed webhook once and queues processing', function () {
    Queue::fake();
    config(['services.pixelforge.webhook_secret' => 'whsec_test']);
    $event = ['id' => 'evt_123', 'type' => 'generation.processing', 'data' => [
        'object' => providerGeneration(['metadata' => ['local_generation_id' => 'local-id']]),
    ]];
    $raw = json_encode($event, JSON_THROW_ON_ERROR);
    $timestamp = now()->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_test');
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        'HTTP_PIXELFORGE_EVENT_ID' => 'evt_123',
    ];

    $this->call('POST', '/webhooks/pixelforge', [], [], [], $headers, $raw)->assertNoContent();
    $this->call('POST', '/webhooks/pixelforge', [], [], [], $headers, $raw)->assertNoContent();

    $this->assertDatabaseCount('webhook_events', 1);
    Queue::assertPushed(ProcessPixelforgeWebhook::class, 2);
});

it('processes a stored success webhook and records its final costs', function () {
    $user = User::factory()->create();
    $generation = generationFor($user, ['pixelforge_generation_id' => 'gen_webhook']);
    $event = WebhookEvent::query()->create([
        'provider_event_id' => 'evt_success',
        'event_type' => 'generation.succeeded',
        'payload' => ['data' => ['object' => providerGeneration([
            'id' => 'gen_webhook', 'status' => 'succeeded',
            'metadata' => ['local_generation_id' => $generation->id],
            'usage' => [
                'input_text_tokens' => 12, 'input_image_tokens' => 0,
                'output_tokens' => 280, 'reasoning_tokens' => 200,
            ],
            'image_url' => 'https://pixelforge.test/webhook.png',
            'image_url_expires_at' => '2026-11-17T12:00:00Z',
            'completed_at' => '2026-11-16T12:00:00Z',
        ])]],
    ]);

    (new ProcessPixelforgeWebhook($event->id))->handle(app(ApplyGenerationResult::class));

    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->final_cost)->toBe('0.01720000000')
        ->and($event->refresh()->processed_at)->not->toBeNull();
});

it('rejects stale or invalid webhook signatures', function () {
    config(['services.pixelforge.webhook_secret' => 'whsec_test']);
    $raw = json_encode(['id' => 'evt_456', 'type' => 'ping'], JSON_THROW_ON_ERROR);
    $timestamp = now()->subMinutes(6)->timestamp;
    $signature = hash_hmac('sha256', $timestamp.'.'.$raw, 'whsec_test');
    $headers = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$timestamp},v1={$signature}",
    ];

    $this->call('POST', '/webhooks/pixelforge', [], [], [], $headers, $raw)->assertBadRequest();
    $this->assertDatabaseCount('webhook_events', 0);
});

function generationFor(User $user, array $overrides = []): Generation
{
    return Generation::query()->create(array_replace([
        'user_id' => $user->id, 'prompt' => 'A lighthouse', 'size' => '1024x1024',
        'quality' => 'low', 'reference_images' => [],
        'idempotency_key' => (string) Str::uuid(),
        'estimated_cost' => '0.02183750000', 'rates' => pricingResponse()['rates'],
        'status' => GenerationStatus::Queued,
    ], $overrides));
}
