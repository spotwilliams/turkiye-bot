<script setup lang="ts">
import { computed } from 'vue';
import type { ReminderType } from '@/types/admin';

const props = defineProps<{ type: ReminderType }>();

const map: Record<ReminderType, string> = {
    preparation: 'bg-sky-50 text-sky-700',
    action: 'bg-orange-50 text-orange-700',
    final: 'bg-rose-50 text-rose-700',
};

const cls = computed(() => map[props.type] ?? 'bg-zinc-100 text-zinc-500');
</script>

<template>
    <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wide', cls]">
        {{ type }}
    </span>
</template>
