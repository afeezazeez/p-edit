<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ImagePlus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import ReferenceImageUrls from '@/components/generations/ReferenceImageUrls.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { estimate, store } from '@/routes/generations';

type Payload = {
    prompt: string;
    size: string;
    quality: string;
    reference_images: string[];
};
type PriceEstimate = {
    estimated_cost: string;
    estimated_min_cost: string;
    estimated_max_cost: string;
    currency: string;
};

const inputs = reactive<Payload>({
    prompt: '',
    size: '1024x1024',
    quality: 'medium',
    reference_images: [],
});
const errors = reactive<Record<string, string>>({});
const touched = reactive({ prompt: false, reference_images: false });
const estimateResult = ref<PriceEstimate | null>(null);
const estimateLoading = ref(false);
const estimateError = ref('');
const isSubmitting = ref(false);
const idempotencyKey = ref(crypto.randomUUID());
const localPromptError = computed(() =>
    touched.prompt && !inputs.prompt.trim()
        ? 'Write a prompt to continue.'
        : '',
);
let estimateTimer: ReturnType<typeof setTimeout> | undefined;
let estimateController: AbortController | undefined;

function invalidReferenceUrl(): string {
    for (const value of inputs.reference_images) {
        if (!value.trim()) continue;
        try {
            if (!['http:', 'https:'].includes(new URL(value).protocol))
                return 'Use a valid http or https URL.';
        } catch {
            return 'Enter a complete image URL starting with https://.';
        }
    }
    return '';
}

async function requestEstimate(): Promise<void> {
    const controller = new AbortController();
    estimateController = controller;
    estimateLoading.value = true;
    estimateError.value = '';
    try {
        const response = await fetch(estimate.url(), {
            method: 'POST',
            credentials: 'same-origin',
            signal: controller.signal,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                ...inputs,
                reference_images: cleanReferences(),
            }),
        });
        if (!response.ok)
            throw new Error('Price estimate is temporarily unavailable.');
        const result = await response.json();
        estimateResult.value = result.data as PriceEstimate;
    } catch (error) {
        if (error instanceof DOMException && error.name === 'AbortError')
            return;
        estimateResult.value = null;
        estimateError.value =
            'Price estimate is temporarily unavailable. Try again in a moment.';
    } finally {
        if (estimateController === controller) estimateLoading.value = false;
    }
}

function csrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((item) => item.startsWith('XSRF-TOKEN='));
    return decodeURIComponent(cookie?.slice('XSRF-TOKEN='.length) ?? '');
}

function cleanReferences(): string[] {
    return inputs.reference_images.filter((url) => url.trim());
}

function queueEstimate(): void {
    clearTimeout(estimateTimer);
    estimateController?.abort();
    estimateLoading.value = false;
    estimateResult.value = null;
    estimateError.value = '';
    if (!inputs.prompt.trim() || invalidReferenceUrl()) return;
    estimateTimer = setTimeout(() => void requestEstimate(), 500);
}

function invalidReferenceIndex(): number {
    return inputs.reference_images.findIndex((value) => {
        if (!value.trim()) return false;
        try {
            return !['http:', 'https:'].includes(new URL(value).protocol);
        } catch {
            return true;
        }
    });
}

function submit(): void {
    touched.prompt = true;
    touched.reference_images = true;
    const invalidIndex = invalidReferenceIndex();
    if (!inputs.prompt.trim() || invalidIndex >= 0) {
        document
            .getElementById(
                !inputs.prompt.trim() ? 'prompt' : `reference-${invalidIndex}`,
            )
            ?.focus();
        return;
    }
    Object.keys(errors).forEach((key) => delete errors[key]);
    router.post(
        store.url(),
        { ...inputs, reference_images: cleanReferences() },
        {
            headers: { 'Idempotency-Key': idempotencyKey.value },
            preserveScroll: true,
            onStart: () => {
                isSubmitting.value = true;
            },
            onError: (serverErrors) => Object.assign(errors, serverErrors),
            onSuccess: () => {
                idempotencyKey.value = crypto.randomUUID();
            },
            onFinish: () => {
                isSubmitting.value = false;
            },
        },
    );
}

function money(value: string, currency: string): string {
    return new Intl.NumberFormat(undefined, {
        style: 'currency',
        currency,
        currencyDisplay: 'narrowSymbol',
        minimumFractionDigits: 4,
        maximumFractionDigits: 4,
    }).format(Number(value));
}

watch(
    () => [
        inputs.prompt,
        inputs.size,
        inputs.quality,
        [...inputs.reference_images],
    ],
    queueEstimate,
    { deep: true },
);
onBeforeUnmount(() => {
    clearTimeout(estimateTimer);
    estimateController?.abort();
});
</script>

<template>
    <form
        novalidate
        @submit.prevent="submit"
        class="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_19rem]"
    >
        <section
            class="space-y-6 rounded-xl border bg-card p-5 shadow-sm sm:p-7"
        >
            <div class="flex items-center justify-between gap-3 border-b pb-4">
                <div>
                    <p class="text-xs font-semibold tracking-wide text-primary">
                        IMAGE DETAILS
                    </p>
                    <h2 class="mt-1 text-xl font-semibold">
                        What should we create?
                    </h2>
                </div>
                <span class="text-xs text-muted-foreground"
                    ><span class="text-destructive">*</span> Required</span
                >
            </div>

            <div class="grid gap-2">
                <Label for="prompt"
                    >Your prompt
                    <span class="text-destructive" aria-hidden="true"
                        >*</span
                    ></Label
                >
                <textarea
                    id="prompt"
                    v-model="inputs.prompt"
                    rows="6"
                    maxlength="4000"
                    required
                    placeholder="Example: A cinematic portrait of Lionel Messi walking into a glowing stadium at sunset…"
                    :aria-invalid="!!(localPromptError || errors.prompt)"
                    aria-describedby="prompt-help prompt-error"
                    class="w-full resize-y rounded-lg border border-input bg-background px-4 py-3 text-sm leading-6 shadow-xs transition outline-none focus-visible:border-ring focus-visible:ring-2 focus-visible:ring-ring/40"
                    @blur="touched.prompt = true"
                />
                <div class="flex items-start justify-between gap-3">
                    <p id="prompt-help" class="text-xs text-muted-foreground">
                        Include the subject, setting, and style you want.
                    </p>
                    <span
                        class="shrink-0 text-xs text-muted-foreground tabular-nums"
                        >{{ inputs.prompt.length }}/4000</span
                    >
                </div>
                <InputError
                    id="prompt-error"
                    :message="localPromptError || errors.prompt"
                />
            </div>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">
                    Image size
                    <span class="text-destructive" aria-hidden="true">*</span>
                </legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    <label
                        class="cursor-pointer rounded-lg border p-3 transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="inputs.size"
                            class="sr-only"
                            type="radio"
                            name="size"
                            value="1024x1024"
                        />
                        <span class="block text-sm font-medium">Square</span
                        ><span class="mt-1 block text-xs text-muted-foreground"
                            >1024 × 1024</span
                        >
                    </label>
                    <label
                        class="cursor-pointer rounded-lg border p-3 transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="inputs.size"
                            class="sr-only"
                            type="radio"
                            name="size"
                            value="1536x1024"
                        />
                        <span class="block text-sm font-medium">Landscape</span
                        ><span class="mt-1 block text-xs text-muted-foreground"
                            >1536 × 1024</span
                        >
                    </label>
                    <label
                        class="cursor-pointer rounded-lg border p-3 transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="inputs.size"
                            class="sr-only"
                            type="radio"
                            name="size"
                            value="1024x1536"
                        />
                        <span class="block text-sm font-medium">Portrait</span
                        ><span class="mt-1 block text-xs text-muted-foreground"
                            >1024 × 1536</span
                        >
                    </label>
                </div>
                <InputError :message="errors.size" />
            </fieldset>

            <fieldset class="space-y-3">
                <legend class="text-sm font-medium">
                    Quality
                    <span class="text-destructive" aria-hidden="true">*</span>
                </legend>
                <div class="grid grid-cols-3 gap-3">
                    <label
                        class="cursor-pointer rounded-lg border p-3 text-center text-sm font-medium transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="inputs.quality"
                            class="sr-only"
                            type="radio"
                            name="quality"
                            value="low"
                        />Low
                    </label>
                    <label
                        class="cursor-pointer rounded-lg border p-3 text-center text-sm font-medium transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="inputs.quality"
                            class="sr-only"
                            type="radio"
                            name="quality"
                            value="medium"
                        />Medium
                    </label>
                    <label
                        class="cursor-pointer rounded-lg border p-3 text-center text-sm font-medium transition hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-primary/5"
                    >
                        <input
                            v-model="inputs.quality"
                            class="sr-only"
                            type="radio"
                            name="quality"
                            value="high"
                        />High
                    </label>
                </div>
                <InputError :message="errors.quality" />
            </fieldset>

            <ReferenceImageUrls
                v-model="inputs.reference_images"
                :errors="errors"
                :touched="touched.reference_images"
                @blur="touched.reference_images = true"
            />
        </section>

        <aside class="space-y-4 lg:sticky lg:top-6">
            <section
                class="rounded-xl border bg-card p-5 shadow-sm"
                aria-live="polite"
            >
                <div class="flex items-center gap-2 text-sm font-semibold">
                    <span class="size-2 rounded-full bg-primary" />Estimated
                    total
                </div>
                <p
                    class="mt-5 text-3xl font-semibold tracking-tight tabular-nums"
                >
                    <span
                        v-if="estimateLoading"
                        class="inline-flex items-center gap-2 text-base text-muted-foreground"
                        ><Spinner />Calculating</span
                    >
                    <span v-else-if="estimateResult"
                        >About
                        {{
                            money(
                                estimateResult.estimated_cost,
                                estimateResult.currency,
                            )
                        }}</span
                    >
                    <span v-else class="text-muted-foreground">—</span>
                </p>
                <p
                    v-if="estimateResult"
                    class="mt-2 text-sm font-medium text-muted-foreground"
                >
                    Between
                    {{
                        money(
                            estimateResult.estimated_min_cost,
                            estimateResult.currency,
                        )
                    }}
                    and
                    {{
                        money(
                            estimateResult.estimated_max_cost,
                            estimateResult.currency,
                        )
                    }}
                </p>
                <p class="mt-2 text-xs leading-5 text-muted-foreground">
                    The final charge is calculated from actual usage after
                    generation. Failed generations incur no charge.
                </p>
                <p
                    v-if="!estimateResult && !estimateLoading"
                    class="mt-2 text-xs text-muted-foreground"
                >
                    Enter a prompt to calculate your estimate.
                </p>
                <p v-if="estimateError" class="mt-3 text-xs text-destructive">
                    {{ estimateError }}
                </p>
                <InputError :message="errors['idempotency_key']" />

                <div class="my-5 border-t" />
                <h3
                    class="text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Request summary
                </h3>
                <dl class="mt-3 space-y-3 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Size</dt>
                        <dd class="text-right font-medium">
                            {{ inputs.size }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Quality</dt>
                        <dd class="text-right font-medium capitalize">
                            {{ inputs.quality }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-muted-foreground">Reference images</dt>
                        <dd class="text-right font-medium">
                            {{ cleanReferences().length }}
                        </dd>
                    </div>
                </dl>
            </section>
            <section class="space-y-3 rounded-xl border bg-muted/40 p-5">
                <Button
                    type="submit"
                    class="min-h-11 w-full"
                    :disabled="isSubmitting"
                    :aria-busy="isSubmitting"
                >
                    <Spinner v-if="isSubmitting" class="mr-2" /><ImagePlus
                        v-else
                        class="mr-2 size-4"
                    />
                    {{ isSubmitting ? 'Submitting…' : 'Generate image' }}
                </Button>
                <p class="text-center text-xs leading-5 text-muted-foreground">
                    You’ll only be charged if the image is successfully created.
                </p>
            </section>
        </aside>
    </form>
</template>
