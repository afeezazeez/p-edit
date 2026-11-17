<script setup lang="ts">
import { computed, ref } from 'vue';
import GenerationStatusBadge from '@/components/generations/GenerationStatusBadge.vue';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

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

const props = defineProps<{ generation: Generation }>();
const detailsOpen = ref(false);
const hasFinalCharge = computed(() => props.generation.final_cost !== null);
const failed = computed(() => props.generation.status === 'failed');

function money(value: string, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    }).format(Number(value));
}

function date(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function openDetails(): void {
    detailsOpen.value = true;
}
</script>

<template>
    <Sheet v-model:open="detailsOpen">
        <article
            role="button"
            tabindex="0"
            class="cursor-pointer overflow-hidden rounded-xl border bg-card"
            :aria-label="`View details for ${generation.prompt}`"
            @click="openDetails"
            @keydown.enter.prevent="openDetails"
            @keydown.space.prevent="openDetails"
        >
            <div
                v-if="generation.image_url"
                class="h-40 w-full overflow-hidden bg-muted sm:h-44"
            >
                <img
                    :src="generation.image_url"
                    :alt="generation.prompt"
                    class="h-full w-full object-cover transition-transform hover:scale-[1.02]"
                    loading="lazy"
                />
            </div>
            <div
                v-else
                class="flex h-40 w-full items-center justify-center bg-muted text-sm text-muted-foreground sm:h-44"
            >
                <span v-if="generation.status === 'failed'"
                    >Image could not be created</span
                >
                <span v-else>Image is being generated…</span>
            </div>
            <div class="space-y-2.5 p-3">
                <div>
                    <p
                        class="line-clamp-2 text-sm font-medium"
                        :title="generation.prompt"
                    >
                        {{ generation.prompt }}
                    </p>
                </div>
                <p
                    v-if="generation.error_message"
                    class="text-sm text-destructive"
                >
                    {{ generation.error_message }}
                </p>
                <div class="space-y-2 text-xs text-muted-foreground">
                    <div
                        class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1"
                    >
                        <span
                            >{{ generation.size }} ·
                            {{ generation.quality }}</span
                        >
                        <GenerationStatusBadge :status="generation.status" />
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span
                            >Est.
                            {{
                                money(
                                    generation.estimated_cost,
                                    generation.currency,
                                )
                            }}</span
                        >
                        <span v-if="failed" class="font-medium text-foreground"
                            >Not charged</span
                        >
                        <span v-else-if="hasFinalCharge">
                            Final
                            {{
                                money(
                                    generation.final_cost!,
                                    generation.currency,
                                )
                            }}
                        </span>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ date(generation.created_at) }}
                </p>
            </div>
        </article>
        <SheetContent class="w-full overflow-y-auto sm:max-w-lg">
            <SheetHeader class="pr-12">
                <SheetTitle class="text-lg">{{ generation.prompt }}</SheetTitle>
                <SheetDescription>Image generation details</SheetDescription>
            </SheetHeader>
            <div class="space-y-5 px-4 pb-6">
                <a
                    v-if="generation.image_url"
                    :href="generation.image_url"
                    target="_blank"
                    rel="noreferrer"
                    class="block cursor-pointer"
                    :aria-label="`Open generated image for ${generation.prompt}`"
                >
                    <img
                        :src="generation.image_url"
                        :alt="generation.prompt"
                        class="max-h-[55vh] w-full rounded-lg border object-contain"
                    />
                </a>
                <div
                    v-else
                    class="flex aspect-square items-center justify-center rounded-lg bg-muted text-sm text-muted-foreground"
                >
                    {{
                        failed
                            ? 'Image could not be created'
                            : 'Image is being generated…'
                    }}
                </div>
                <dl class="divide-y rounded-lg border text-sm">
                    <div class="flex justify-between gap-4 p-3">
                        <dt class="text-muted-foreground">Status</dt>
                        <dd>
                            <GenerationStatusBadge
                                :status="generation.status"
                            />
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <dt class="text-muted-foreground">Size</dt>
                        <dd>{{ generation.size }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <dt class="text-muted-foreground">Quality</dt>
                        <dd class="capitalize">{{ generation.quality }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <dt class="text-muted-foreground">Estimated cost</dt>
                        <dd>
                            {{
                                money(
                                    generation.estimated_cost,
                                    generation.currency,
                                )
                            }}
                        </dd>
                    </div>
                    <div v-if="failed" class="flex justify-between gap-4 p-3">
                        <dt class="text-muted-foreground">Final charge</dt>
                        <dd>Not charged</dd>
                    </div>
                    <div
                        v-else-if="hasFinalCharge"
                        class="flex justify-between gap-4 p-3"
                    >
                        <dt class="text-muted-foreground">Final charge</dt>
                        <dd>
                            {{
                                money(
                                    generation.final_cost!,
                                    generation.currency,
                                )
                            }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 p-3">
                        <dt class="text-muted-foreground">Created</dt>
                        <dd class="text-right">
                            {{ date(generation.created_at) }}
                        </dd>
                    </div>
                </dl>
                <p
                    v-if="generation.error_message"
                    class="rounded-lg bg-destructive/10 p-3 text-sm text-destructive"
                >
                    {{ generation.error_message }}
                </p>
            </div>
        </SheetContent>
    </Sheet>
</template>
