<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import GenerationForm from '@/components/generations/GenerationForm.vue';
import { create, index } from '@/routes/generations';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Generations', href: index() },
            { title: 'Create image', href: create() },
        ],
    },
});
</script>

<template>
    <Head title="Create an image" />
    <main class="mx-auto w-full max-w-6xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
        <Link
            :href="index()"
            class="inline-flex min-h-11 items-center gap-2 rounded-md pr-3 text-sm font-medium text-muted-foreground transition hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <ArrowLeft class="size-4" aria-hidden="true" />Back to generations
        </Link>
        <header class="space-y-2">
            <p class="text-sm font-medium text-primary">NEW IMAGE</p>
            <h1 class="text-3xl font-semibold tracking-tight">
                Create an image
            </h1>
            <p class="max-w-2xl text-sm text-muted-foreground">
                Describe the image and choose its settings. You’ll see the
                estimated price before you submit.
            </p>
        </header>
        <GenerationForm />
    </main>
</template>
