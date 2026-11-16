<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <main class="h-svh overflow-hidden bg-background text-foreground lg:grid lg:grid-cols-2">
        <section
            class="relative hidden h-full flex-col justify-between overflow-hidden bg-primary p-8 text-primary-foreground lg:flex xl:p-10"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-40 -left-32 size-[34rem] rounded-full bg-primary-foreground/10 blur-3xl"
            />
            <div
                aria-hidden="true"
                class="pointer-events-none absolute right-[-10rem] bottom-[-12rem] size-[32rem] rounded-full bg-primary-foreground/10 blur-3xl"
            />

            <Link
                :href="home()"
                class="relative z-10 inline-flex min-h-11 w-fit items-center gap-3 rounded-md font-semibold tracking-tight focus-visible:ring-2 focus-visible:ring-primary-foreground focus-visible:ring-offset-2 focus-visible:ring-offset-primary"
            >
                <span class="grid size-9 grid-cols-2 gap-1 rounded-xl border border-primary-foreground/20 bg-primary-foreground/10 p-2">
                    <span class="rounded-sm bg-primary-foreground" />
                    <span class="rounded-sm bg-primary-foreground/55" />
                    <span class="rounded-sm bg-primary-foreground/55" />
                    <span class="rounded-sm bg-primary-foreground" />
                </span>
                    <span>Image <span class="font-normal opacity-70">Studio</span></span>
            </Link>

            <div class="relative z-10 mx-auto w-full max-w-xl py-6">
                <h1 class="max-w-lg text-4xl leading-tight font-semibold tracking-tight xl:text-5xl">
                    Start with an idea.
                    <span class="block opacity-65">See where it takes you.</span>
                </h1>
                <p class="mt-5 max-w-md text-base leading-7 opacity-75">
                    Describe the image you have in mind, choose a size and
                    quality, and review the estimated price before generating.
                </p>

                <div
                    class="mt-7 overflow-hidden rounded-2xl border border-primary-foreground/15 bg-primary-foreground/5 p-3 shadow-2xl"
                >
                    <div
                        class="relative flex aspect-[1.55/1] items-end overflow-hidden rounded-xl bg-gradient-to-br from-sky-300 via-indigo-400 to-fuchsia-500 p-5 sm:p-7"
                        role="img"
                        aria-label="Abstract sunset illustration used as a preview"
                    >
                        <div
                            aria-hidden="true"
                            class="absolute inset-0 bg-gradient-to-t from-slate-950/65 via-transparent to-white/10"
                        />
                        <div
                            aria-hidden="true"
                            class="absolute top-[19%] left-[58%] size-16 rounded-full bg-amber-100/90 shadow-xl shadow-white/30 sm:size-20"
                        />
                        <div
                            aria-hidden="true"
                            class="absolute right-[-8%] bottom-[-38%] left-[-8%] h-[64%] rounded-[50%] bg-indigo-950/60 blur-[1px]"
                        />
                        <div
                            aria-hidden="true"
                            class="absolute right-[-16%] bottom-[-53%] left-[-16%] h-[64%] rounded-[50%] bg-slate-950/65"
                        />
                        <div class="relative z-10">
                            <p class="text-xs font-medium tracking-wide text-white/75">IMAGE PREVIEW</p>
                            <p class="mt-1 text-lg font-medium text-white sm:text-xl">
                                A quiet coast at dusk
                            </p>
                        </div>
                        <span
                            class="absolute top-4 right-4 rounded-full border border-white/25 bg-slate-950/20 px-2.5 py-1 text-[11px] font-medium text-white backdrop-blur"
                        >
                            1024 × 1024
                        </span>
                    </div>
                    <div class="flex items-center justify-between gap-4 px-2 pt-4 pb-1">
                        <div>
                            <p class="text-sm font-medium">Your prompt, your choices</p>
                            <p class="mt-1 text-xs opacity-65">Your idea, brought into focus</p>
                        </div>
                        <span class="rounded-full bg-primary-foreground/10 px-3 py-1.5 text-xs font-medium">
                            Price shown up front
                        </span>
                    </div>
                </div>

            </div>

        </section>

        <section class="flex h-full flex-col px-6 py-5 sm:px-10 lg:px-14">
            <Link
                :href="home()"
                class="inline-flex min-h-11 w-fit items-center gap-2 rounded-md font-semibold tracking-tight focus-visible:ring-2 focus-visible:ring-ring lg:hidden"
            >
                <span class="grid size-8 grid-cols-2 gap-1 rounded-lg bg-primary p-1.5 text-primary-foreground">
                    <span class="rounded-sm bg-current" />
                    <span class="rounded-sm bg-current opacity-55" />
                    <span class="rounded-sm bg-current opacity-55" />
                    <span class="rounded-sm bg-current" />
                </span>
                Image Studio
            </Link>

            <div class="flex flex-1 items-center justify-center py-5">
                <div class="w-full max-w-sm">
                    <div class="mb-8 space-y-2">
                        <p class="text-sm font-medium text-muted-foreground">
                            Your creative workspace
                        </p>
                        <h2 class="text-3xl font-semibold tracking-tight">
                            {{ title }}
                        </h2>
                        <p class="text-sm leading-6 text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>

        </section>
    </main>
</template>
