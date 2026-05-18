<script setup lang="ts">
withDefaults(defineProps<{ label: string; value?: string | null; mono?: boolean }>(), {
    value: null,
    mono: false,
});
</script>

<template>
    <div class="flex items-center gap-1.5">
        <span class="text-[11px] text-zinc-400 font-medium">{{ label }}</span>
        <span class="text-zinc-300">·</span>
        <slot>
            <span :class="['text-[11px] text-zinc-600', mono && 'font-mono']">{{ value }}</span>
        </slot>
    </div>
</template>
