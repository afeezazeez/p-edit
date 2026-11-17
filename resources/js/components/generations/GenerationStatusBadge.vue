<script setup lang="ts">
import { Badge } from '@/components/ui/badge';

const props = defineProps<{ status: string }>();

const labels: Record<string, string> = {
    pending: 'Submitting',
    queued: 'Queued',
    processing: 'Generating',
    succeeded: 'Complete',
    failed: 'Failed',
};

const styles: Record<string, string> = {
    pending:
        'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200',
    queued: 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
    processing:
        'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-200',
    succeeded:
        'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-200',
    failed: 'border-destructive/30 bg-destructive/10 text-destructive',
};
</script>

<template>
    <Badge variant="outline" :class="styles[props.status] ?? ''">
        {{ labels[props.status] ?? props.status }}
    </Badge>
</template>
