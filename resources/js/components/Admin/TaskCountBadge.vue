<script setup lang="ts">
defineProps<{ count: number }>();
</script>

<template>
    <span v-if="!count" class="text-xs text-zinc-300">—</span>
    <span
        v-else
        class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 text-indigo-700 text-[11px] font-semibold"
    >
        {{ count }}
    </span>
</template>
