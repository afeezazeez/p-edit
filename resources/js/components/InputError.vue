<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div
        v-if="message"
        role="alert"
        class="rounded-md border border-destructive/30 bg-destructive/5 px-3 py-2"
    >
        <p class="text-sm text-destructive">
            {{ message }}
        </p>
    </div>
</template>
