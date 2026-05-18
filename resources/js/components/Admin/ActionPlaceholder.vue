<script setup lang="ts">
withDefaults(defineProps<{ label?: string }>(), { label: 'Actions' });
</script>

<template>
    <div
        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded border border-dashed border-zinc-300 text-[11px] text-zinc-400 select-none cursor-default"
    >
        <span class="w-3 h-3 rounded-sm border border-current opacity-50"></span>
        {{ label }}
    </div>
</template>
