<script setup lang="ts">
import { Skeleton } from '@/components/ui/skeleton';
</script>

<template>
    <div
        aria-label="Loading generations"
        aria-busy="true"
        class="grid justify-start gap-4"
        style="
            grid-template-columns: repeat(
                auto-fill,
                minmax(min(100%, 20rem), 20rem)
            );
        "
    >
        <article
            v-for="item in 3"
            :key="item"
            class="overflow-hidden rounded-xl border bg-card"
        >
            <Skeleton class="h-40 w-full rounded-none sm:h-44" />
            <div class="space-y-3 p-4">
                <Skeleton class="h-4 w-3/4" />
                <Skeleton class="h-4 w-1/2" />
                <Skeleton class="h-8 w-24" />
            </div>
        </article>
        <span class="sr-only">Loading your image history…</span>
    </div>
</template>
