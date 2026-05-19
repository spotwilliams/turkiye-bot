<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import ActionPlaceholder from '@/components/Admin/ActionPlaceholder.vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import ContentCard from '@/components/Admin/ContentCard.vue';
import EmptyState from '@/components/Admin/EmptyState.vue';
import MetaField from '@/components/Admin/MetaField.vue';
import PendingCard from '@/components/Admin/PendingCard.vue';
import ProcessedBadge from '@/components/Admin/ProcessedBadge.vue';
import SummaryCallout from '@/components/Admin/SummaryCallout.vue';
import TaskCard from '@/components/Admin/TaskCard.vue';
import TaskCountBadge from '@/components/Admin/TaskCountBadge.vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import admin from '@/routes/admin';
import type { AdminMessageDetail } from '@/types/admin';

const props = defineProps<{ message: AdminMessageDetail }>();

const created = computed(() => fmtIstanbul(props.message.created_at) ?? '');
const hasTasks = computed(() => props.message.tasks.length > 0);
</script>

<template>
    <Head :title="message.ref" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb
            :items="[
                { label: 'Messages', href: admin.messages.index().url },
                { label: message.ref },
            ]"
        />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">{{ message.ref }}</h1>
            <ActionPlaceholder label="Actions" />
        </div>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mt-3">
            <MetaField label="Chat ID" :value="message.telegram_chat_id" mono />
            <MetaField label="Created" :value="created" mono />
            <MetaField label="Status">
                <ProcessedBadge
                    :status="message.status"
                    :processed-at="message.processed_at"
                    :failed-at="message.failed_at"
                />
            </MetaField>
            <MetaField label="Tasks">
                <TaskCountBadge :count="message.tasks.length" />
            </MetaField>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-6">
        <div
            v-if="message.status === 'failed'"
            class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            <div class="font-semibold">Processing failed</div>
            <div class="mt-1 font-mono whitespace-pre-wrap break-all">{{ message.failure_reason }}</div>
            <div class="mt-1 text-xs text-red-600">{{ fmtIstanbul(message.failed_at) }}</div>
        </div>
        <div class="flex gap-6 items-start flex-wrap">
            <div class="flex-1 min-w-0 space-y-4" style="min-width: 320px;">
                <ContentCard lang="🇹🇷 Turkish" :text="message.original_turkish" />
                <ContentCard v-if="message.english" lang="🇬🇧 English" :text="message.english" />
                <PendingCard v-else lang="🇬🇧 English" />
                <ContentCard v-if="message.spanish" lang="🇪🇸 Spanish" :text="message.spanish" />
                <PendingCard v-else lang="🇪🇸 Spanish" />
                <SummaryCallout v-if="message.summary" :text="message.summary" />
            </div>

            <div class="w-80 shrink-0 space-y-3" style="min-width: 280px;">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-sm font-semibold text-zinc-800">
                        Tasks
                        <span v-if="hasTasks" class="ml-2 text-xs font-normal text-zinc-400">
                            {{ message.tasks.length }}
                        </span>
                    </span>
                </div>
                <template v-if="hasTasks">
                    <TaskCard v-for="task in message.tasks" :key="task.id" :task="task" />
                </template>
                <EmptyState
                    v-else
                    icon="✅"
                    title="No tasks extracted"
                    description="The AI agent found no actionable tasks in this message."
                />
            </div>
        </div>
    </div>
</template>
