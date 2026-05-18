<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import CategoryBadge from '@/components/Admin/CategoryBadge.vue';
import MetaField from '@/components/Admin/MetaField.vue';
import ReminderTypePill from '@/components/Admin/ReminderTypePill.vue';
import StatusPill from '@/components/Admin/StatusPill.vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import admin from '@/routes/admin';
import type { AdminReminderDetail } from '@/types/admin';

const props = defineProps<{ reminder: AdminReminderDetail }>();

const scheduled = computed(() => fmtIstanbul(props.reminder.scheduled_at) ?? '');
const sentAt = computed(() => fmtIstanbul(props.reminder.sent_at));
</script>

<template>
    <Head :title="reminder.ref" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb
            :items="[
                { label: 'Reminders', href: admin.reminders.index().url },
                { label: reminder.ref },
            ]"
        />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">{{ reminder.ref }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mt-3">
            <MetaField label="Scheduled" :value="scheduled" mono />
            <MetaField label="Type">
                <ReminderTypePill :type="reminder.type" />
            </MetaField>
            <MetaField label="Status">
                <span
                    :class="[
                        'text-[10px] font-medium px-2 py-0.5 rounded-full',
                        reminder.sent ? 'bg-green-50 text-green-700' : 'bg-zinc-100 text-zinc-500',
                    ]"
                >
                    {{ reminder.sent ? 'Sent' : 'Pending' }}
                </span>
            </MetaField>
            <MetaField v-if="sentAt" label="Sent at" :value="sentAt" mono />
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-6 space-y-4">
        <div class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 mb-1.5">
                Reminder text
            </div>
            <p class="text-sm text-zinc-800 leading-relaxed">{{ reminder.text }}</p>
        </div>

        <div v-if="reminder.task" class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 mb-2">
                Task
            </div>
            <div class="flex items-center gap-2 mb-2 flex-wrap">
                <Link
                    :href="admin.tasks.show(reminder.task.id).url"
                    class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                >
                    {{ reminder.task.ref }}
                </Link>
                <CategoryBadge :category="reminder.task.category" />
                <StatusPill :status="reminder.task.status" />
            </div>
            <p class="text-sm text-zinc-700">{{ reminder.task.description }}</p>
            <p v-if="reminder.task.due_date" class="text-[11px] text-zinc-400 font-mono mt-1">
                Due {{ reminder.task.due_date }}
            </p>

            <div v-if="reminder.task.message" class="mt-3 pt-3 border-t border-zinc-100">
                <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 mb-1">
                    Source message
                </div>
                <Link
                    :href="admin.messages.show(reminder.task.message.id).url"
                    class="text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                >
                    {{ reminder.task.message.ref }}
                </Link>
                <p v-if="reminder.task.message.summary" class="text-[11px] text-zinc-500 mt-1">
                    {{ reminder.task.message.summary }}
                </p>
            </div>
        </div>
    </div>
</template>
