<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

type Props = {
    className?: HTMLAttributes['class'];
};

defineProps<Props>();
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 40 40"
        :class="className"
        v-bind="$attrs"
    >
        <rect x="3" y="3" width="15" height="15" rx="3" fill="currentColor" />
        <rect x="22" y="3" width="15" height="15" rx="3" fill="currentColor" opacity=".55" />
        <rect x="3" y="22" width="15" height="15" rx="3" fill="currentColor" opacity=".55" />
        <rect x="22" y="22" width="15" height="15" rx="3" fill="currentColor" />
    </svg>
</template>
