<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { fmtDate, truncate } from '@/composables/useIstanbulDate';
import type { AdminTaskRow } from '@/types/admin';
import AssignedBadge from './AssignedBadge.vue';
import CategoryBadge from './CategoryBadge.vue';
import StatusPill from './StatusPill.vue';

const props = defineProps<{ task: AdminTaskRow; href: string; isLast: boolean }>();

const dueLabel = computed(() => {
    if (!props.task.due_date) {
return '—';
}

    const d = fmtDate(props.task.due_date);

    return props.task.due_time ? `${d} · ${props.task.due_time}` : d;
});
const description = computed(() => truncate(props.task.description, 64));
</script>

<template>
    <Link
        :href="href"
        :class="[
            'grid grid-cols-[72px_1fr_110px_110px_120px_110px_64px] gap-4 items-center px-5 py-3 cursor-pointer transition-colors hover:bg-zinc-50 bg-white',
            !isLast && 'border-b border-zinc-100',
        ]"
    >
        <span class="font-mono text-[11px] text-zinc-400">{{ task.ref }}</span>
        <span class="text-sm text-zinc-700 truncate">{{ description }}</span>
        <CategoryBadge :category="task.category" />
        <StatusPill :status="task.status" />
        <span class="font-mono text-[11px] text-zinc-500">{{ dueLabel }}</span>
        <AssignedBadge :assigned-to="task.assigned_to" />
        <span class="font-mono text-[11px] text-zinc-400 text-right">{{ task.reminders_count }}</span>
    </Link>
</template>
