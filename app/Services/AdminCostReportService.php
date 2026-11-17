<?php

namespace App\Services;

use App\Pixelforge\PixelforgeClient;
use App\Pixelforge\PixelforgeException;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type DailyRecord array{date: string, user_charges: string, provider_cost: string, profit: string}
 * @phpstan-type ReconciledDailyRecord array{date: string, user_charges: string, provider_cost: string, profit: string, pixelforge_cost: string, reconciles: bool}
 * @phpstan-type UserRecord array{date: string, user_id: int, user_name: string, email: string, user_charges: string, provider_cost: string, profit: string}
 * @phpstan-type ReportTotals array{user_charges: string, provider_cost: string, pixelforge_cost: string, profit: string, reconciles: bool}
 */
class AdminCostReportService
{
    public function __construct(private readonly PixelforgeClient $client) {}

    /** @return array{daily: list<ReconciledDailyRecord>, users: list<UserRecord>, totals: ReportTotals, usage_error: ?string} */
    public function report(string $startDate, string $endDate): array
    {
        $daily = $this->dailyRecords($startDate, $endDate);
        $users = $this->userRecords($startDate, $endDate);
        $usage = $this->providerUsage($startDate, $endDate);
        $daily = $this->attachReconciliation($daily, $usage['days']);

        return [
            'daily' => $daily,
            'users' => $users,
            'totals' => $this->totals($daily),
            'usage_error' => $usage['error'],
        ];
    }

    /** @return list<DailyRecord> */
    private function dailyRecords(string $startDate, string $endDate): array
    {
        return DB::table('generations')
            ->where('status', 'succeeded')
            ->whereBetween('completed_at', [$this->utcStart($startDate), $this->utcEnd($endDate)])
            ->selectRaw('DATE(completed_at) as date, SUM(final_cost) as user_charges, SUM(provider_cost) as provider_cost')
            ->groupByRaw('DATE(completed_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => $this->dailyRow($row))
            ->values()
            ->all();
    }

    /** @return list<UserRecord> */
    private function userRecords(string $startDate, string $endDate): array
    {
        return DB::table('generations')
            ->join('users', 'users.id', '=', 'generations.user_id')
            ->where('generations.status', 'succeeded')
            ->whereBetween('generations.completed_at', [$this->utcStart($startDate), $this->utcEnd($endDate)])
            ->selectRaw('DATE(generations.completed_at) as date, users.id as user_id, users.name as user_name, users.email, SUM(generations.final_cost) as user_charges, SUM(generations.provider_cost) as provider_cost')
            ->groupByRaw('DATE(generations.completed_at), users.id, users.name, users.email')
            ->orderByDesc('date')
            ->orderBy('users.name')
            ->get()
            ->map(fn ($row) => [
                'date' => (string) $row->date,
                'user_id' => (int) $row->user_id,
                'user_name' => (string) $row->user_name,
                'email' => (string) $row->email,
                'user_charges' => $this->money($row->user_charges),
                'provider_cost' => $this->money($row->provider_cost),
                'profit' => $this->subtract($row->user_charges, $row->provider_cost),
            ])
            ->values()
            ->all();
    }

    /** @return array{days: array<string, string>, error: ?string} */
    private function providerUsage(string $startDate, string $endDate): array
    {
        try {
            $response = $this->client->usage($startDate, $endDate);
        } catch (PixelforgeException) {
            return ['days' => [], 'error' => 'Pixelforge usage could not be loaded.'];
        }

        $days = $this->usageDays($response['data'] ?? []);

        return ['days' => $days, 'error' => null];
    }

    /**
     * @return array<string, string>
     */
    private function usageDays(mixed $records): array
    {
        $days = [];
        foreach (is_array($records) ? $records : [] as $record) {
            if (! is_array($record) || ! is_string($record['date'] ?? null)) {
                continue;
            }
            $cost = $record['cost_usd'] ?? '0';
            if (is_numeric($cost)) {
                $days[$record['date']] = $this->money($cost);
            }
        }

        return $days;
    }

    /**
     * @param  list<DailyRecord>  $daily
     * @param  array<string, string>  $providerDays
     * @return list<ReconciledDailyRecord>
     */
    private function attachReconciliation(array $daily, array $providerDays): array
    {
        $localDays = [];
        foreach ($daily as $row) {
            $localDays[$row['date']] = $row;
        }
        $dates = array_unique(array_merge(array_keys($providerDays), array_column($daily, 'date')));
        sort($dates);
        $result = [];

        foreach ($dates as $date) {
            $row = $localDays[$date] ?? [
                'date' => $date,
                'user_charges' => '0.00000000000',
                'provider_cost' => '0.00000000000',
                'profit' => '0.00000000000',
            ];
            $providerReported = $providerDays[$date] ?? '0';
            $row['pixelforge_cost'] = $providerReported;
            $row['reconciles'] = $this->decimal($row['provider_cost']) === $this->decimal($providerReported);
            $result[] = $row;
        }

        return $result;
    }

    /**
     * @return DailyRecord
     */
    private function dailyRow(object $row): array
    {
        $record = (array) $row;
        $userCharges = $this->money($record['user_charges'] ?? '0');
        $providerCost = $this->money($record['provider_cost'] ?? '0');

        return [
            'date' => (string) ($record['date'] ?? ''),
            'user_charges' => $userCharges,
            'provider_cost' => $providerCost,
            'profit' => $this->subtract($userCharges, $providerCost),
        ];
    }

    /** @param list<ReconciledDailyRecord> $daily
     * @return ReportTotals
     */
    private function totals(array $daily): array
    {
        $totals = [
            'user_charges' => '0.00000000000',
            'provider_cost' => '0.00000000000',
            'pixelforge_cost' => '0.00000000000',
            'profit' => '0.00000000000',
            'reconciles' => true,
        ];
        foreach ($daily as $row) {
            $totals['user_charges'] = $this->add($totals['user_charges'], $row['user_charges']);
            $totals['provider_cost'] = $this->add($totals['provider_cost'], $row['provider_cost']);
            $totals['pixelforge_cost'] = $this->add($totals['pixelforge_cost'], $row['pixelforge_cost']);
            $totals['profit'] = $this->add($totals['profit'], $row['profit']);
            $totals['reconciles'] = $totals['reconciles'] && $row['reconciles'];
        }

        return $totals;
    }

    private function decimal(string $value): string
    {
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        $fraction = rtrim($fraction, '0');

        return $fraction === '' ? $whole : $whole.'.'.$fraction;
    }

    private function money(mixed $value): string
    {
        $amount = (string) $value;
        if (! is_numeric($amount) || str_contains(strtolower($amount), 'inf') || str_contains(strtolower($amount), 'nan')) {
            return '0';
        }

        return $this->numericDecimal($amount);
    }

    private function numericDecimal(string $value): string
    {
        if (preg_match('/^-?\d+(\.\d+)?$/', $value) !== 1) {
            return '0';
        }

        return $value;
    }

    /** @param numeric-string $left @param numeric-string $right */
    private function add(string $left, string $right): string
    {
        return bcadd($this->money($left), $this->money($right), 11);
    }

    private function subtract(mixed $left, mixed $right): string
    {
        return bcsub($this->money($left), $this->money($right), 11);
    }

    private function utcStart(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'UTC')->startOfDay();
    }

    private function utcEnd(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'UTC')->endOfDay();
    }
}
