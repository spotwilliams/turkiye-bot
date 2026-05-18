<script setup lang="ts">
import StatusPill from './StatusPill.vue';

defineProps<{ lang: string }>();
</script>

<template>
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden opacity-60">
        <div class="px-4 py-2.5 bg-zinc-50 border-b border-zinc-100 flex items-center justify-between">
            <span class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wide">{{ lang }}</span>
            <StatusPill status="pending" />
        </div>
        <div class="px-4 py-3">
            <div class="h-3 bg-zinc-100 rounded w-4/5 mb-2"></div>
            <div class="h-3 bg-zinc-100 rounded w-3/5"></div>
        </div>
    </div>
</template>
