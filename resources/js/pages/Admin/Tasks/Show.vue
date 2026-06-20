<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import AssignedBadge from '@/components/Admin/AssignedBadge.vue';
import CategoryBadge from '@/components/Admin/CategoryBadge.vue';
import EmptyState from '@/components/Admin/EmptyState.vue';
import MetaField from '@/components/Admin/MetaField.vue';
import ReminderItem from '@/components/Admin/ReminderItem.vue';
import StatusPill from '@/components/Admin/StatusPill.vue';
import TaskWriteActions from '@/components/Admin/TaskWriteActions.vue';
import { fmtDate, fmtIstanbul } from '@/composables/useIstanbulDate';
import admin from '@/routes/admin';
import type { AdminTaskDetail } from '@/types/admin';

const props = defineProps<{ task: AdminTaskDetail }>();

const dueLabel = computed(() => {
    if (!props.task.due_date) {
return '—';
}

    const d = fmtDate(props.task.due_date);

    return props.task.due_time ? `${d} · ${props.task.due_time}` : d;
});
const created = computed(() => fmtIstanbul(props.task.created_at) ?? '');
const completed = computed(() => fmtIstanbul(props.task.completed_at));
const hasReminders = computed(() => props.task.reminders.length > 0);
</script>

<template>
    <Head :title="task.ref" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb
            :items="[
                { label: 'Tasks', href: admin.tasks.index().url },
                { label: task.ref },
            ]"
        />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">{{ task.ref }}</h1>
            <TaskWriteActions :task="task" />
        </div>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mt-3">
            <MetaField label="Chat ID" :value="task.telegram_chat_id" mono />
            <MetaField label="Created" :value="created" mono />
            <MetaField v-if="completed" label="Completed" :value="completed" mono />
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-6">
        <div class="flex gap-6 items-start flex-wrap">
            <div class="flex-1 min-w-0 space-y-4" style="min-width: 320px;">
                <div class="bg-white rounded-xl border border-zinc-200 p-4">
                    <div class="flex items-center gap-2 flex-wrap mb-3">
                        <CategoryBadge :category="task.category" />
                        <StatusPill :status="task.status" />
                        <AssignedBadge :assigned-to="task.assigned_to" />
                    </div>
                    <p class="text-sm text-zinc-800 font-medium leading-snug mb-3">
                        {{ task.description }}
                    </p>
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-zinc-500 font-mono">
                        <span>Due {{ dueLabel }}</span>
                        <span v-if="task.amount" class="font-medium text-zinc-700">
                            {{ task.amount }} {{ task.currency }}
                        </span>
                    </div>
                </div>

                <div v-if="task.message" class="bg-white rounded-xl border border-zinc-200 p-4">
                    <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 mb-1.5">
                        Source message
                    </div>
                    <Link
                        :href="admin.messages.show(task.message.id).url"
                        class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                    >
                        {{ task.message.ref }}
                    </Link>
                    <p v-if="task.message.summary" class="text-sm text-zinc-600 mt-1 leading-snug">
                        {{ task.message.summary }}
                    </p>
                </div>
            </div>

            <div class="w-80 shrink-0 space-y-3" style="min-width: 280px;">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-semibold text-zinc-800">
                        Reminders
                        <span v-if="hasReminders" class="ml-2 text-xs font-normal text-zinc-400">
                            {{ task.reminders.length }}
                        </span>
                    </span>
                </div>
                <div v-if="hasReminders" class="bg-white rounded-xl border border-zinc-200 divide-y divide-zinc-100 overflow-hidden">
                    <ReminderItem v-for="r in task.reminders" :key="r.id" :reminder="r" editable />
                </div>
                <EmptyState
                    v-else
                    icon="🔕"
                    title="No reminders"
                    description="No reminders are scheduled for this task."
                />
            </div>
        </div>
    </div>
</template>
