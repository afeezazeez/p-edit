<script setup lang="ts">
import { computed } from 'vue';
import { Link2 } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import ReferenceUrlField from '@/components/generations/ReferenceUrlField.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';

const props = defineProps<{
    modelValue: string[];
    errors: Record<string, string>;
    touched: boolean;
}>();
const emit = defineEmits<{
    'update:modelValue': [value: string[]];
    blur: [];
}>();
const validationError = computed(() => {
    for (const value of props.modelValue) {
        if (!value.trim()) continue;
        try {
            if (!['http:', 'https:'].includes(new URL(value).protocol))
                return 'Use a valid http or https URL.';
        } catch {
            return 'Enter a complete image URL starting with https://.';
        }
    }
    return '';
});

function update(index: number, value: string): void {
    const urls = [...props.modelValue];
    urls[index] = value;
    emit('update:modelValue', urls);
}

function add(): void {
    if (props.modelValue.length < 4)
        emit('update:modelValue', [...props.modelValue, '']);
}

function remove(index: number): void {
    emit(
        'update:modelValue',
        props.modelValue.filter((_, itemIndex) => itemIndex !== index),
    );
}

function fieldError(index: number): string {
    return (
        (props.touched ? validationError.value : '') ||
        props.errors[`reference_images.${index}`] ||
        ''
    );
}
</script>

<template>
    <div class="space-y-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <Label
                    >Reference image URLs
                    <span class="font-normal text-muted-foreground"
                        >(optional, up to 4)</span
                    ></Label
                >
                <p class="mt-1 text-xs text-muted-foreground">
                    Add image links to guide the result’s look or subject.
                </p>
            </div>
            <Button
                v-if="props.modelValue.length < 4"
                type="button"
                variant="outline"
                size="sm"
                @click="add"
                ><Link2 class="mr-2 size-4" />Add URL</Button
            >
        </div>
        <ReferenceUrlField
            v-if="props.modelValue.length > 0"
            :index="0"
            :model-value="props.modelValue[0] ?? ''"
            :error="fieldError(0)"
            @update:model-value="update(0, $event)"
            @blur="emit('blur')"
            @remove="remove(0)"
        />
        <ReferenceUrlField
            v-if="props.modelValue.length > 1"
            :index="1"
            :model-value="props.modelValue[1] ?? ''"
            :error="fieldError(1)"
            @update:model-value="update(1, $event)"
            @blur="emit('blur')"
            @remove="remove(1)"
        />
        <ReferenceUrlField
            v-if="props.modelValue.length > 2"
            :index="2"
            :model-value="props.modelValue[2] ?? ''"
            :error="fieldError(2)"
            @update:model-value="update(2, $event)"
            @blur="emit('blur')"
            @remove="remove(2)"
        />
        <ReferenceUrlField
            v-if="props.modelValue.length > 3"
            :index="3"
            :model-value="props.modelValue[3] ?? ''"
            :error="fieldError(3)"
            @update:model-value="update(3, $event)"
            @blur="emit('blur')"
            @remove="remove(3)"
        />
        <InputError :message="props.errors.reference_images" />
    </div>
</template>
