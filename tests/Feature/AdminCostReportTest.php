<?php

use App\Models\Generation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('blocks regular users from the operator cost report', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/reports/costs')
        ->assertForbidden();
});

it('reports per-day and per-user revenue, provider costs, profit, and provider reconciliation', function () {
    Http::fake(['*usage*' => Http::response([
        'object' => 'usage',
        'start_date' => '2026-11-16',
        'end_date' => '2026-11-17',
        'data' => [
            ['date' => '2026-11-16', 'generations' => 2, 'cost_usd' => '0.300000000'],
            ['date' => '2026-11-17', 'generations' => 0, 'cost_usd' => '0.000000000'],
        ],
        'total' => ['generations' => 2, 'cost_usd' => '0.300000000'],
    ])]);
    $admin = User::factory()->create();
    $admin->role = 'admin';
    $admin->save();
    $other = User::factory()->create();

    completedGeneration($admin, '0.20000000000', '0.25000000000', '2026-11-16 23:59:59');
    completedGeneration($other, '0.10000000000', '0.12500000000', '2026-11-16 12:00:00');
    completedGeneration($other, '0.50000000000', '0.00000000000', '2026-11-16 12:30:00', 'failed');
    completedGeneration($other, '0.50000000000', '0.62500000000', '2026-11-17 00:00:00');

    $this->actingAs($admin)
        ->get('/admin/reports/costs?start_date=2026-11-16&end_date=2026-11-17')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CostReport')
            ->where('report.totals.user_charges', '1.00000000000')
            ->where('report.totals.provider_cost', '0.80000000000')
            ->where('report.totals.profit', '0.20000000000')
            ->where('report.totals.pixelforge_cost', '0.30000000000')
            ->where('report.totals.reconciles', false)
            ->has('report.daily', 2)
            ->where('report.daily.0.date', '2026-11-16')
            ->where('report.daily.0.reconciles', true)
            ->where('report.daily.1.date', '2026-11-17')
            ->where('report.daily.1.reconciles', false)
            ->has('report.users', 3)
            ->where('report.users.0.user_id', $other->id)
            ->where('report.users.0.profit', '0.12500000000'));

    Http::assertSent(fn ($request) => str_contains($request->url(), 'start_date=2026-11-16&end_date=2026-11-17'));
});

it('rejects a date range whose end precedes its start', function () {
    $admin = User::factory()->create();
    $admin->role = 'admin';
    $admin->save();

    $this->actingAs($admin)
        ->get('/admin/reports/costs?start_date=2026-11-17&end_date=2026-11-16')
        ->assertSessionHasErrors('end_date');
});

it('uses the default date range when the report is loaded without filters', function () {
    CarbonImmutable::setTestNow('2026-11-17 10:00:00', 'UTC');
    Http::fake(['*usage*' => Http::response(['data' => []])]);

    $admin = User::factory()->create();
    $admin->role = 'admin';
    $admin->save();

    $this->actingAs($admin)
        ->get('/admin/reports/costs')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/CostReport')
            ->where('startDate', '2026-11-11')
            ->where('endDate', '2026-11-17'));

    CarbonImmutable::setTestNow();
});

function completedGeneration(User $user, string $providerCost, string $finalCost, string $completedAt, string $status = 'succeeded'): Generation
{
    return Generation::query()->create([
        'user_id' => $user->id,
        'prompt' => 'Test prompt',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_images' => [],
        'status' => $status,
        'idempotency_key' => (string) Str::uuid(),
        'estimated_cost' => '0.50000000000',
        'provider_cost' => $providerCost,
        'final_cost' => $finalCost,
        'completed_at' => $completedAt,
    ]);
}
