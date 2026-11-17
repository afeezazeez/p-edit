<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';

type DailyRow = {
    date: string;
    user_charges: string;
    provider_cost: string;
    pixelforge_cost: string;
    profit: string;
    reconciles: boolean;
};
type UserRow = {
    date: string;
    user_id: number;
    user_name: string;
    email: string;
    user_charges: string;
    provider_cost: string;
    profit: string;
};
type Report = {
    daily: DailyRow[];
    users: UserRow[];
    totals: {
        user_charges: string;
        provider_cost: string;
        pixelforge_cost: string;
        profit: string;
        reconciles: boolean;
    };
    usage_error: string | null;
};

const props = defineProps<{
    startDate: string;
    endDate: string;
    report: Report;
}>();
const startDate = ref(props.startDate);
const endDate = ref(props.endDate);

function filterReport(): void {
    router.get(
        '/admin/reports/costs',
        {
            start_date: startDate.value,
            end_date: endDate.value,
        },
        { preserveState: true, preserveScroll: true },
    );
}

function clearFilters(): void {
    startDate.value = props.startDate;
    endDate.value = props.endDate;
    router.get('/admin/reports/costs', {}, { preserveScroll: true });
}

function money(value: string): string {
    const [whole, fraction = ''] = value.split('.');
    const groupedWhole = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',');

    return `$${groupedWhole}.${fraction.padEnd(9, '0')}`;
}

function day(value: string): string {
    return new Intl.DateTimeFormat('en-US', {
        dateStyle: 'medium',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}
</script>

<template>
    <Head title="Admin cost report" />
    <main class="mx-auto w-full max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <header class="space-y-2">
            <p class="text-sm font-medium text-primary">OPERATOR</p>
            <h1 class="text-3xl font-semibold tracking-tight">Cost report</h1>
            <p class="max-w-3xl text-sm text-muted-foreground">
                User charges are compared with Pixelforge costs. Profit is
                before any costs other than Pixelforge.
            </p>
        </header>

        <form
            class="flex flex-wrap items-end gap-3"
            @submit.prevent="filterReport"
        >
            <label class="grid gap-1 text-sm font-medium">
                From (UTC)
                <input
                    v-model="startDate"
                    type="date"
                    required
                    class="h-11 rounded-md border border-input bg-background px-3"
                />
            </label>
            <label class="grid gap-1 text-sm font-medium">
                To (UTC)
                <input
                    v-model="endDate"
                    type="date"
                    required
                    class="h-11 rounded-md border border-input bg-background px-3"
                />
            </label>
            <Button type="submit" class="min-h-11">Apply dates</Button>
            <Button
                type="button"
                variant="outline"
                class="min-h-11"
                @click="clearFilters"
            >
                Clear filters
            </Button>
        </form>

        <div
            v-if="report.usage_error"
            role="alert"
            class="rounded-lg border border-destructive/40 bg-destructive/5 p-4 text-sm"
        >
            {{ report.usage_error }} The app totals are shown, but
            reconciliation is unavailable.
        </div>

        <section
            aria-label="Selected date range totals"
            class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4"
        >
            <article
                v-for="item in [
                    {
                        label: 'User charges',
                        value: report.totals.user_charges,
                    },
                    {
                        label: 'Pixelforge cost (app)',
                        value: report.totals.provider_cost,
                    },
                    {
                        label: 'Profit before other costs',
                        value: report.totals.profit,
                    },
                    {
                        label: 'Pixelforge usage report',
                        value: report.totals.pixelforge_cost,
                    },
                ]"
                :key="item.label"
                class="rounded-xl border bg-card p-5"
            >
                <p class="text-sm text-muted-foreground">{{ item.label }}</p>
                <p class="mt-2 text-xl font-semibold tabular-nums">
                    {{ money(item.value) }}
                </p>
            </article>
        </section>

        <section class="space-y-3" aria-labelledby="daily-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="daily-heading" class="text-xl font-semibold">
                    Daily totals
                </h2>
                <p
                    v-if="!report.usage_error"
                    class="text-sm"
                    :class="
                        report.totals.reconciles
                            ? 'text-primary'
                            : 'text-destructive'
                    "
                >
                    {{
                        report.totals.reconciles
                            ? 'Provider costs match Pixelforge usage'
                            : 'Provider costs do not match Pixelforge usage'
                    }}
                </p>
            </div>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="p-3">UTC day</th>
                            <th class="p-3">User charges</th>
                            <th class="p-3">Provider cost (app)</th>
                            <th class="p-3">Pixelforge usage</th>
                            <th class="p-3">Profit</th>
                            <th class="p-3">Reconciliation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.daily"
                            :key="row.date"
                            class="border-t"
                        >
                            <td class="p-3">{{ day(row.date) }}</td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.user_charges) }}
                            </td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.provider_cost) }}
                            </td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.pixelforge_cost) }}
                            </td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.profit) }}
                            </td>
                            <td class="p-3">
                                {{
                                    report.usage_error
                                        ? 'Unavailable'
                                        : row.reconciles
                                          ? 'Matched'
                                          : 'Mismatch'
                                }}
                            </td>
                        </tr>
                        <tr v-if="report.daily.length === 0">
                            <td
                                colspan="6"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No completed generations for these dates.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="space-y-3" aria-labelledby="users-heading">
            <h2 id="users-heading" class="text-xl font-semibold">
                Per user and day
            </h2>
            <div class="overflow-x-auto rounded-xl border">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="bg-muted/50 text-muted-foreground">
                        <tr>
                            <th class="p-3">UTC day</th>
                            <th class="p-3">User</th>
                            <th class="p-3">User charges</th>
                            <th class="p-3">Pixelforge cost</th>
                            <th class="p-3">Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="row in report.users"
                            :key="`${row.date}-${row.user_id}`"
                            class="border-t"
                        >
                            <td class="p-3">{{ day(row.date) }}</td>
                            <td class="p-3">
                                <span class="block font-medium">{{
                                    row.user_name
                                }}</span
                                ><span class="text-muted-foreground">{{
                                    row.email
                                }}</span>
                            </td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.user_charges) }}
                            </td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.provider_cost) }}
                            </td>
                            <td class="p-3 tabular-nums">
                                {{ money(row.profit) }}
                            </td>
                        </tr>
                        <tr v-if="report.users.length === 0">
                            <td
                                colspan="5"
                                class="p-8 text-center text-muted-foreground"
                            >
                                No completed generations for these dates.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</template>
