<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePoll } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import GenerationCard from '@/components/generations/GenerationCard.vue';
import GenerationEmptyState from '@/components/generations/GenerationEmptyState.vue';
import GenerationSkeleton from '@/components/generations/GenerationSkeleton.vue';
import { create, index as generationsIndex } from '@/routes/generations';

type Generation = {
    id: string;
    prompt: string;
    size: string;
    quality: string;
    status: string;
    estimated_cost: string;
    final_cost: string | null;
    currency: string;
    image_url: string | null;
    image_url_expires_at: string | null;
    error_message: string | null;
    created_at: string;
};

const props = defineProps<{
    filters: { status: string };
    generations: {
        data: Generation[];
        meta: {
            current_page: number;
            last_page: number;
            total: number;
            links: Array<{
                url: string | null;
                label: string;
                active: boolean;
            }>;
        };
    };
}>();
const statusFilters = [
    { label: 'All', value: 'all' },
    { label: 'Queued', value: 'queued' },
    { label: 'Processing', value: 'processing' },
    { label: 'Succeeded', value: 'succeeded' },
    { label: 'Failed', value: 'failed' },
];
const loadingHistory = ref(false);
const hasGenerations = computed(() => props.generations.data.length > 0);
const hasActiveGenerations = computed(() =>
    props.generations.data.some((generation) =>
        ['pending', 'queued', 'processing'].includes(generation.status),
    ),
);
const { start: startPolling, stop: stopPolling } = usePoll(
    3000,
    { only: ['generations'] },
    { autoStart: false },
);
let removeStartListener: (() => void) | undefined;
let removeFinishListener: (() => void) | undefined;

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Generations', href: generationsIndex() }],
    },
});

onMounted(() => {
    removeStartListener = router.on('start', (event) => {
        const visit = event.detail.visit;
        const destination = new URL(visit.url);
        const nextStatus = destination.searchParams.get('status') ?? 'all';
        if (
            visit.method === 'get' &&
            destination.pathname === '/generations' &&
            !visit.only?.includes('generations') &&
            nextStatus === props.filters.status
        ) {
            loadingHistory.value = true;
        }
    });
    removeFinishListener = router.on('finish', () => {
        loadingHistory.value = false;
    });
    if (hasActiveGenerations.value) startPolling();
});
watch(hasActiveGenerations, (active) => {
    if (active) startPolling();
    else stopPolling();
});
onBeforeUnmount(() => {
    removeStartListener?.();
    removeFinishListener?.();
    stopPolling();
});
</script>

<template>
    <Head title="Your generations" />
    <main class="mx-auto w-full max-w-7xl space-y-8 px-4 py-6 sm:px-6 lg:px-8">
        <header
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <p class="text-sm font-medium text-primary">IMAGE WORKSPACE</p>
                <h1 class="mt-1 text-3xl font-semibold tracking-tight">
                    Your generations
                </h1>
                <p class="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Create images and keep track of their progress and final
                    price.
                </p>
            </div>
            <Button v-if="hasGenerations" as-child class="min-h-11">
                <Link :href="create()"
                    ><ImagePlus class="mr-2 size-4" />Create image</Link
                >
            </Button>
        </header>

        <section aria-label="Generation history">
            <nav
                class="mb-6 flex flex-wrap gap-2"
                aria-label="Filter generations by status"
            >
                <Link
                    v-for="filter in statusFilters"
                    :key="filter.value"
                    :href="
                        filter.value === 'all'
                            ? generationsIndex()
                            : generationsIndex({
                                  query: { status: filter.value },
                              })
                    "
                    preserve-scroll
                    :aria-current="
                        filters.status === filter.value ? 'page' : undefined
                    "
                    class="rounded-full border px-4 py-2 text-sm transition-colors"
                    :class="
                        filters.status === filter.value
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-border text-muted-foreground hover:bg-accent hover:text-accent-foreground'
                    "
                >
                    {{ filter.label }}
                </Link>
            </nav>
            <GenerationSkeleton v-if="loadingHistory" />
            <GenerationEmptyState
                v-else-if="!hasGenerations && filters.status === 'all'"
            />
            <div
                v-else-if="!hasGenerations"
                class="rounded-xl border border-dashed p-10 text-center"
            >
                <p class="font-medium">No {{ filters.status }} generations</p>
                <p class="mt-1 text-sm text-muted-foreground">
                    Try another status filter.
                </p>
            </div>
            <div
                v-else
                class="grid justify-start gap-4"
                style="
                    grid-template-columns: repeat(
                        auto-fill,
                        minmax(min(100%, 20rem), 20rem)
                    );
                "
            >
                <GenerationCard
                    v-for="generation in generations.data"
                    :key="generation.id"
                    :generation="generation"
                />
            </div>
        </section>

        <nav
            v-if="generations.meta.last_page > 1"
            aria-label="Generation history pages"
            class="flex flex-wrap justify-center gap-2 pt-2"
        >
            <Link
                v-for="link in generations.meta.links"
                :key="link.label"
                :href="link.url ?? generationsIndex()"
                :aria-current="link.active ? 'page' : undefined"
                :aria-disabled="!link.url"
                class="rounded-md border px-3 py-2 text-sm"
                :class="
                    link.active
                        ? 'bg-primary text-primary-foreground'
                        : !link.url
                          ? 'pointer-events-none opacity-50'
                          : 'hover:bg-accent'
                "
                v-html="link.label"
            />
        </nav>
    </main>
</template>
