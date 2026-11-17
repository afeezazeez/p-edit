<script setup lang="ts">
import { X } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    index: number;
    modelValue: string;
    error: string;
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string];
    blur: [];
    remove: [];
}>();
</script>

<template>
    <div class="flex items-start gap-2">
        <div class="min-w-0 flex-1">
            <Input
                :id="`reference-${props.index}`"
                :aria-label="`Reference image URL ${props.index + 1}`"
                :model-value="props.modelValue"
                type="url"
                inputmode="url"
                placeholder="https://example.com/image.png"
                :aria-invalid="!!props.error"
                @update:model-value="emit('update:modelValue', String($event))"
                @blur="emit('blur')"
            />
            <InputError :message="props.error" />
        </div>
        <Button
            type="button"
            variant="ghost"
            size="icon"
            :aria-label="`Remove reference image ${props.index + 1}`"
            @click="emit('remove')"
            ><X class="size-4"
        /></Button>
    </div>
</template>
