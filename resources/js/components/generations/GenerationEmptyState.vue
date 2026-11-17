<script setup lang="ts">
import { ImagePlus } from '@lucide/vue';
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { create } from '@/routes/generations';
</script>

<template>
    <section
        class="flex min-h-64 flex-col items-center justify-center rounded-xl border border-dashed bg-card px-6 py-12 text-center"
    >
        <div class="mb-4 rounded-full bg-muted p-3 text-muted-foreground">
            <ImagePlus class="size-6" aria-hidden="true" />
        </div>
        <h2 class="text-lg font-semibold">No images yet</h2>
        <p class="mt-2 max-w-sm text-sm text-muted-foreground">
            Your generated images will appear here after you create one.
        </p>
        <Button as-child class="mt-5 min-h-11">
            <Link :href="create()"
                ><ImagePlus class="mr-2 size-4" />Create an image</Link
            >
        </Button>
    </section>
</template>
