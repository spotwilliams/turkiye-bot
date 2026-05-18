<script setup lang="ts">
defineProps<{ text: string }>();
</script>

<template>
    <div class="rounded-xl border border-indigo-100 bg-indigo-50 px-4 py-3 flex gap-3">
        <span class="text-indigo-400 text-base mt-0.5 shrink-0">✦</span>
        <div>
            <div class="text-[11px] font-semibold uppercase tracking-wide text-indigo-400 mb-1">AI Summary</div>
            <p class="text-sm text-indigo-900 leading-relaxed">{{ text }}</p>
        </div>
    </div>
</template>
