<script setup lang="ts">
withDefaults(defineProps<{ icon?: string; title: string; description?: string }>(), {
    icon: '📭',
    description: undefined,
});
</script>

<template>
    <div class="flex flex-col items-center justify-center py-20 select-none">
        <div class="w-12 h-12 rounded-xl bg-zinc-100 flex items-center justify-center text-2xl mb-4">
            {{ icon }}
        </div>
        <div class="text-sm font-semibold text-zinc-700 mb-1">{{ title }}</div>
        <div v-if="description" class="text-xs text-zinc-400 text-center max-w-xs leading-relaxed">
            {{ description }}
        </div>
    </div>
</template>
