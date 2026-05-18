<script setup lang="ts">
import { computed } from 'vue';
import type { TaskCategory } from '@/types/admin';

const props = defineProps<{ category: TaskCategory }>();

const map: Record<TaskCategory, { emoji: string; label: string; cls: string }> = {
    money: { emoji: '💰', label: 'Money', cls: 'bg-yellow-50 text-yellow-800 ring-yellow-200' },
    homework: { emoji: '📚', label: 'Homework', cls: 'bg-sky-50 text-sky-800 ring-sky-200' },
    item: { emoji: '🎒', label: 'Item', cls: 'bg-violet-50 text-violet-800 ring-violet-200' },
    event: { emoji: '📅', label: 'Event', cls: 'bg-indigo-50 text-indigo-800 ring-indigo-200' },
    other: { emoji: '📌', label: 'Other', cls: 'bg-zinc-100 text-zinc-600 ring-zinc-200' },
};

const cat = computed(() => map[props.category] ?? map.other);
</script>

<template>
    <span :class="['inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[11px] font-medium ring-1 ring-inset', cat.cls]">
        <span>{{ cat.emoji }}</span>
        <span>{{ cat.label }}</span>
    </span>
</template>
