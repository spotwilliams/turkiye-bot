<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import EmptyState from '@/components/Admin/EmptyState.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import ReminderTypePill from '@/components/Admin/ReminderTypePill.vue';
import { fmtIstanbul, truncate } from '@/composables/useIstanbulDate';
import admin from '@/routes/admin';
import type { AdminReminderRow, AdminReminderFilters, Paginator, ReminderType } from '@/types/admin';

const props = defineProps<{
    reminders: Paginator<AdminReminderRow>;
    filters: AdminReminderFilters;
}>();

const headers = ['ID', 'Scheduled', 'Type', 'Status', 'Task', 'Text'];
const isEmpty = computed(() => props.reminders.data.length === 0);

const sentOpts = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'sent', label: 'Sent' },
];
const typeOpts: Array<{ value: ReminderType | ''; label: string }> = [
    { value: '', label: 'All' },
    { value: 'preparation', label: 'Preparation' },
    { value: 'action', label: 'Action' },
    { value: 'final', label: 'Final' },
];
const windowOpts = [
    { value: '', label: 'Any' },
    { value: 'due', label: 'Due now' },
    { value: 'upcoming', label: 'Upcoming' },
];

function applyFilter(field: 'sent' | 'type' | 'window', value: string): void {
    const next: Record<string, string> = {
        sent: props.filters.sent ?? '',
        type: props.filters.type ?? '',
        window: props.filters.window ?? '',
    };
    next[field] = value;
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== ''));
    router.get(admin.reminders.index().url, params, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Reminders" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb :items="[{ label: 'Admin' }, { label: 'Reminders' }]" />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">Reminders</h1>
            <span class="text-xs text-zinc-400 font-mono">
                {{ reminders.total === 0 ? '0 reminders' : `${reminders.total} total` }}
            </span>
        </div>
        <div class="flex items-center gap-3 mt-3 flex-wrap">
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Sent
                <select
                    :value="filters.sent ?? ''"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white"
                    @change="(e) => applyFilter('sent', (e.target as HTMLSelectElement).value)"
                >
                    <option v-for="o in sentOpts" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </label>
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Type
                <select
                    :value="filters.type ?? ''"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white"
                    @change="(e) => applyFilter('type', (e.target as HTMLSelectElement).value)"
                >
                    <option v-for="o in typeOpts" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </label>
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Window
                <select
                    :value="filters.window ?? ''"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white"
                    @change="(e) => applyFilter('window', (e.target as HTMLSelectElement).value)"
                >
                    <option v-for="o in windowOpts" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </label>
        </div>
    </div>

    <div class="flex-1 px-8 py-6 overflow-y-auto">
        <div v-if="isEmpty" class="bg-white rounded-xl border border-zinc-200">
            <EmptyState icon="🔕" title="No reminders" description="Reminders generated for tasks will appear here." />
        </div>
        <div v-else class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
            <div class="grid grid-cols-[72px_160px_110px_90px_140px_1fr] gap-4 px-5 py-2.5 bg-zinc-50 border-b border-zinc-200">
                <span
                    v-for="h in headers"
                    :key="h"
                    class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400"
                >
                    {{ h }}
                </span>
            </div>
            <Link
                v-for="(rem, i) in reminders.data"
                :key="rem.id"
                :href="admin.reminders.show(rem.id).url"
                :class="[
                    'grid grid-cols-[72px_160px_110px_90px_140px_1fr] gap-4 items-center px-5 py-3 cursor-pointer transition-colors hover:bg-zinc-50 bg-white',
                    i !== reminders.data.length - 1 && 'border-b border-zinc-100',
                ]"
            >
                <span class="font-mono text-[11px] text-zinc-400">{{ rem.ref }}</span>
                <span class="font-mono text-[11px] text-zinc-500">{{ fmtIstanbul(rem.scheduled_at) }}</span>
                <ReminderTypePill :type="rem.type" />
                <span
                    :class="[
                        'text-[10px] font-medium px-2 py-0.5 rounded-full inline-flex items-center justify-center w-fit',
                        rem.sent ? 'bg-green-50 text-green-700' : 'bg-zinc-100 text-zinc-500',
                    ]"
                >
                    {{ rem.sent ? 'Sent' : 'Pending' }}
                </span>
                <span class="font-mono text-[11px] text-zinc-500 truncate">
                    {{ rem.task?.ref ?? '—' }}
                </span>
                <span class="text-[11px] text-zinc-600 truncate">{{ truncate(rem.text, 80) }}</span>
            </Link>
        </div>

        <Pagination v-if="!isEmpty" :paginator="reminders" />
    </div>
</template>
