<script setup lang="ts">
import { computed } from 'vue';
import type { TaskStatus } from '@/types/admin';

const props = defineProps<{ status: TaskStatus }>();

const styles: Record<TaskStatus, string> = {
    pending: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/25',
    completed: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/25',
    cancelled: 'bg-zinc-100 text-zinc-500 ring-1 ring-inset ring-zinc-300',
    processing: 'bg-blue-50 text-blue-700 ring-1 ring-inset ring-blue-400/30',
};

const cls = computed(() => styles[props.status] ?? styles.pending);
</script>

<template>
    <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium tracking-wide uppercase', cls]">
        {{ status }}
    </span>
</template>
