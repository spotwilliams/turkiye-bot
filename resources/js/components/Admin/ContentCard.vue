<script setup lang="ts">
defineProps<{ lang: string; text: string }>();
</script>

<template>
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-4 py-2.5 bg-zinc-50 border-b border-zinc-100">
            <span class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wide">{{ lang }}</span>
        </div>
        <p class="px-4 py-3 text-sm text-zinc-700 leading-relaxed whitespace-pre-line">{{ text }}</p>
    </div>
</template>
