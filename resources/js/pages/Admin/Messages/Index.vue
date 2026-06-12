<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import EmptyState from '@/components/Admin/EmptyState.vue';
import MessageRow from '@/components/Admin/MessageRow.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import PasteMessageForm from '@/components/Admin/PasteMessageForm.vue';
import admin from '@/routes/admin';
import type { AdminMessageRow, AdminMessageFilters, Paginator } from '@/types/admin';

const props = defineProps<{
    messages: Paginator<AdminMessageRow>;
    filters?: AdminMessageFilters;
}>();

const headers = ['ID', 'Chat ID', 'Summary', 'Tasks', 'Processed', 'Created'];
const isEmpty = computed(() => props.messages.data.length === 0);
const chatId = ref(props.filters?.chat_id ?? '');

watch(
    () => props.filters?.chat_id ?? '',
    (val) => {
        chatId.value = val;
    },
);

// Surface async processing: while any pasted message is still pending, poll so
// the row flips to processed/failed without a manual refresh.
const hasPending = computed(() => props.messages.data.some((m) => m.status === 'pending'));
let pollTimer: ReturnType<typeof setInterval> | undefined;

watch(
    hasPending,
    (pending) => {
        if (pending && !pollTimer) {
            pollTimer = setInterval(() => {
                router.reload({ only: ['messages'] });
            }, 3000);
        } else if (!pending && pollTimer) {
            clearInterval(pollTimer);
            pollTimer = undefined;
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (pollTimer) {
        clearInterval(pollTimer);
    }
});

const processedOpts = [
    { value: '', label: 'All' },
    { value: 'processed', label: 'Processed' },
    { value: 'pending', label: 'Pending' },
];

function applyFilter(field: 'chat_id' | 'processed', value: string): void {
    const next: Record<string, string> = {
        chat_id: props.filters?.chat_id ?? '',
        processed: props.filters?.processed ?? '',
    };
    next[field] = value;
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== ''));
    router.get(admin.messages.index().url, params, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Messages" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb :items="[{ label: 'Admin' }, { label: 'Messages' }]" />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">Messages</h1>
            <div class="flex items-center gap-3">
                <span class="text-xs text-zinc-400 font-mono">
                    {{ messages.total === 0 ? '0 messages' : `${messages.total} total` }}
                </span>
            </div>
        </div>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Chat ID
                <input
                    v-model="chatId"
                    type="text"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white w-40 font-mono"
                    placeholder="all"
                    @keyup.enter="applyFilter('chat_id', chatId.trim())"
                    @blur="applyFilter('chat_id', chatId.trim())"
                />
            </label>
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Processed
                <select
                    :value="filters?.processed ?? ''"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white"
                    @change="(e) => applyFilter('processed', (e.target as HTMLSelectElement).value)"
                >
                    <option v-for="o in processedOpts" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </label>
        </div>
    </div>

    <div class="flex-1 px-8 py-6 overflow-y-auto">
        <PasteMessageForm />

        <div v-if="isEmpty" class="bg-white rounded-xl border border-zinc-200">
            <EmptyState
                icon="📨"
                title="No messages yet"
                description="Messages ingested from Telegram school groups will appear here once the bot is running."
            />
        </div>
        <div v-else class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
            <div class="grid grid-cols-[64px_160px_1fr_72px_160px_140px] gap-4 px-5 py-2.5 bg-zinc-50 border-b border-zinc-200">
                <span
                    v-for="h in headers"
                    :key="h"
                    class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400"
                >
                    {{ h }}
                </span>
            </div>
            <MessageRow
                v-for="(msg, i) in messages.data"
                :key="msg.id"
                :msg="msg"
                :href="admin.messages.show(msg.id).url"
                :is-last="i === messages.data.length - 1"
            />
        </div>

        <Pagination v-if="!isEmpty" :paginator="messages" />
    </div>
</template>
