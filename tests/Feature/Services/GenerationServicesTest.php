<?php

use App\Enums\GenerationStatus;
use App\Jobs\ProcessPixelforgeWebhook;
use App\Models\Generation;
use App\Models\User;
use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeWebhookService;
use App\Pixelforge\PriceCalculator;
use App\Pixelforge\WebhookRejectedException;
use App\Services\ApplyGenerationResult;
use App\Services\GenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('calculates estimates through the generation service using the client pricing', function () {
    $client = Mockery::mock(PixelforgeClient::class);
    $client->shouldReceive('pricing')->once()->andReturn(servicePricing());
    $service = generationService($client);

    $estimate = $service->estimate(serviceInput());

    expect($estimate['estimated_cost'])->toBe('0.01793125000')
        ->and($estimate['estimated_min_cost'])->toBe('0.01402500000')
        ->and($estimate['estimated_max_cost'])->toBe('0.02183750000')
        ->and($estimate['provider_cost'])->toBe('0.01434500000');
});

it('creates and records a generation through the generation service', function () {
    $user = User::factory()->create();
    $client = Mockery::mock(PixelforgeClient::class);
    $client->shouldReceive('pricing')->once()->andReturn(servicePricing());
    $client->shouldReceive('createGeneration')->once()->withArgs(function (array $payload, string $key) {
        return $key === 'a5a6ffca-58fd-4f75-9c93-cbec23a92d04'
            && $payload['prompt'] === 'A lighthouse'
            && $payload['metadata']['local_generation_id'] !== '';
    })->andReturn(serviceProviderGeneration());

    $generation = generationService($client)->create(
        $user, serviceInput(), 'a5a6ffca-58fd-4f75-9c93-cbec23a92d04',
    );

    expect($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->pixelforge_generation_id)->toBe('gen_service');
    $this->assertDatabaseHas('generations', [
        'id' => $generation->id, 'user_id' => $user->id, 'status' => 'queued',
    ]);
});

it('returns only the requested user’s generations from generation service history', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    serviceGenerationRecord($owner);
    serviceGenerationRecord($owner);
    serviceGenerationRecord($other);
    $client = Mockery::mock(PixelforgeClient::class);

    $history = generationService($client)->history($owner);

    expect($history->total())->toBe(2);
});

it('applies a successful result once and does not move it backwards', function () {
    $generation = serviceGenerationRecord(User::factory()->create(), [
        'pixelforge_generation_id' => 'gen_service',
    ]);
    $provider = serviceProviderGeneration([
        'status' => 'succeeded',
        'metadata' => ['local_generation_id' => $generation->id],
        'usage' => [
            'input_text_tokens' => 12,
            'input_image_tokens' => 0,
            'output_tokens' => 280,
            'reasoning_tokens' => 200,
        ],
        'image_url' => 'https://pixelforge.test/result.png',
        'image_url_expires_at' => '2026-11-17T12:00:00Z',
        'completed_at' => '2026-11-16T12:00:00Z',
    ]);
    $service = app(ApplyGenerationResult::class);

    $service->apply($provider);
    $service->apply($provider);
    $service->apply(serviceProviderGeneration([
        'status' => 'processing',
        'metadata' => ['local_generation_id' => $generation->id],
    ]));

    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost)->toBe('0.01376000000')
        ->and($generation->final_cost)->toBe('0.01720000000');
});

it('marks failed results free through the result service', function () {
    $generation = serviceGenerationRecord(User::factory()->create(), [
        'pixelforge_generation_id' => 'gen_service',
    ]);

    app(ApplyGenerationResult::class)->apply(serviceProviderGeneration([
        'status' => 'failed',
        'metadata' => ['local_generation_id' => $generation->id],
        'error' => ['code' => 'generation_failed', 'message' => 'Generation failed.'],
        'completed_at' => '2026-11-16T12:00:00Z',
    ]));

    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed)
        ->and($generation->provider_cost)->toBe('0.00000000000')
        ->and($generation->final_cost)->toBe('0.00000000000');
});

it('stores a signed webhook only once and queues it for processing', function () {
    Queue::fake();
    config(['services.pixelforge.webhook_secret' => 'whsec_service']);
    $body = json_encode([
        'id' => 'evt_service', 'type' => 'generation.processing',
        'data' => ['object' => serviceProviderGeneration()],
    ], JSON_THROW_ON_ERROR);
    $timestamp = now()->timestamp;
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_service');
    $service = app(PixelforgeWebhookService::class);

    $service->receive($body, $signature, 'evt_service');
    $service->receive($body, $signature, 'evt_service');

    $this->assertDatabaseCount('webhook_events', 1);
    Queue::assertPushed(ProcessPixelforgeWebhook::class, 2);
});

it('rejects an invalid webhook signature before storing an event', function () {
    config(['services.pixelforge.webhook_secret' => 'whsec_service']);

    expect(fn () => app(PixelforgeWebhookService::class)->receive(
        '{"id":"evt_invalid","type":"ping"}', 't=1,v1=invalid', 'evt_invalid',
    ))->toThrow(WebhookRejectedException::class);

    $this->assertDatabaseCount('webhook_events', 0);
});

function generationService(PixelforgeClient $client): GenerationService
{
    return new GenerationService($client, app(PriceCalculator::class), app(ApplyGenerationResult::class));
}

function serviceInput(): array
{
    return ['prompt' => 'A lighthouse', 'size' => '1024x1024', 'quality' => 'low'];
}

function serviceGenerationRecord(User $user, array $overrides = []): Generation
{
    return Generation::query()->create(array_replace([
        'user_id' => $user->id,
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => [],
        'idempotency_key' => (string) Str::uuid(),
        'estimated_cost' => '0.02183750000',
        'rates' => servicePricing()['rates'],
        'status' => GenerationStatus::Queued,
    ], $overrides));
}

function servicePricing(): array
{
    return [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => ['1024x1024' => 280, '1536x1024' => 420, '1024x1536' => 420],
                'medium' => ['1024x1024' => 1100, '1536x1024' => 1650, '1024x1536' => 1650],
                'high' => ['1024x1024' => 4300, '1536x1024' => 6450, '1024x1536' => 6450],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];
}

function serviceProviderGeneration(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 'gen_service',
        'status' => 'queued',
        'metadata' => ['local_generation_id' => ''],
        'rates' => servicePricing()['rates'],
        'usage' => null,
        'image_url' => null,
        'image_url_expires_at' => null,
        'error' => null,
        'started_at' => null,
        'completed_at' => null,
    ], $overrides);
}
