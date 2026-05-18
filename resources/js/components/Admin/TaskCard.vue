<script setup lang="ts">
import { computed, ref } from 'vue';
import { fmtDate } from '@/composables/useIstanbulDate';
import type { AdminTask } from '@/types/admin';
import AssignedBadge from './AssignedBadge.vue';
import CategoryBadge from './CategoryBadge.vue';
import ReminderItem from './ReminderItem.vue';
import StatusPill from './StatusPill.vue';

const props = defineProps<{ task: AdminTask }>();

const expanded = ref(true);
const hasReminders = computed(() => props.task.reminders.length > 0);
const dueLabel = computed(() => {
    if (!props.task.due_date) {
return null;
}

    const date = fmtDate(props.task.due_date);

    return props.task.due_time ? `${date} · ${props.task.due_time}` : date;
});
const reminderLabel = computed(() => {
    const n = props.task.reminders.length;

    if (n === 0) {
return 'No reminders';
}

    return `${n} reminder${n > 1 ? 's' : ''}`;
});
</script>

<template>
    <div class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
        <div class="px-4 pt-3 pb-3">
            <div class="flex items-start justify-between gap-2 mb-2">
                <div class="flex items-center gap-2 flex-wrap">
                    <CategoryBadge :category="task.category" />
                    <StatusPill :status="task.status" />
                </div>
                <AssignedBadge :assigned-to="task.assigned_to" />
            </div>

            <p class="text-sm text-zinc-800 font-medium leading-snug mb-2">{{ task.description }}</p>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-zinc-400">
                <span v-if="dueLabel" class="font-mono">Due {{ dueLabel }}</span>
                <span v-if="task.amount" class="font-mono font-medium text-zinc-600">
                    {{ task.amount }} {{ task.currency }}
                </span>
            </div>
        </div>

        <div class="border-t border-zinc-100">
            <button
                type="button"
                class="w-full flex items-center justify-between px-4 py-2 text-[11px] text-zinc-400 hover:bg-zinc-50 transition-colors"
                @click="expanded = !expanded"
            >
                <span class="font-medium">{{ reminderLabel }}</span>
                <span
                    v-if="hasReminders"
                    class="text-zinc-300 inline-block transition-transform"
                    :style="{ transform: expanded ? 'rotate(180deg)' : 'rotate(0deg)' }"
                >▾</span>
            </button>

            <div v-if="expanded && hasReminders" class="border-t border-zinc-100 divide-y divide-zinc-100">
                <ReminderItem v-for="r in task.reminders" :key="r.id" :reminder="r" />
            </div>

            <div
                v-if="expanded && !hasReminders"
                class="px-4 py-3 text-[11px] text-zinc-300 italic border-t border-zinc-100"
            >
                No reminders scheduled
            </div>
        </div>
    </div>
</template>
