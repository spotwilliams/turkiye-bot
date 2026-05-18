<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import ActionPlaceholder from '@/components/Admin/ActionPlaceholder.vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import EmptyState from '@/components/Admin/EmptyState.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import TaskRow from '@/components/Admin/TaskRow.vue';
import admin from '@/routes/admin';
import type { AdminTaskRow, AdminTaskFilters, Paginator, TaskStatus, TaskCategory } from '@/types/admin';

const props = defineProps<{
    tasks: Paginator<AdminTaskRow>;
    filters: AdminTaskFilters;
}>();

const headers = ['ID', 'Description', 'Category', 'Status', 'Due', 'Assigned', 'Rem.'];
const isEmpty = computed(() => props.tasks.data.length === 0);

const statuses: Array<{ value: TaskStatus | ''; label: string }> = [
    { value: '', label: 'All' },
    { value: 'pending', label: 'Pending' },
    { value: 'completed', label: 'Completed' },
    { value: 'cancelled', label: 'Cancelled' },
];
const categories: Array<{ value: TaskCategory | ''; label: string }> = [
    { value: '', label: 'All' },
    { value: 'money', label: 'Money' },
    { value: 'homework', label: 'Homework' },
    { value: 'item', label: 'Item' },
    { value: 'event', label: 'Event' },
    { value: 'other', label: 'Other' },
];

function applyFilter(field: 'status' | 'category', value: string): void {
    const next: Record<string, string> = {
        status: props.filters.status ?? '',
        category: props.filters.category ?? '',
    };
    next[field] = value;
    const params = Object.fromEntries(Object.entries(next).filter(([, v]) => v !== ''));
    router.get(admin.tasks.index().url, params, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <Head title="Tasks" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb :items="[{ label: 'Admin' }, { label: 'Tasks' }]" />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">Tasks</h1>
            <div class="flex items-center gap-3">
                <span class="text-xs text-zinc-400 font-mono">
                    {{ tasks.total === 0 ? '0 tasks' : `${tasks.total} total` }}
                </span>
                <ActionPlaceholder label="Actions" />
            </div>
        </div>
        <div class="flex items-center gap-3 mt-3">
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Status
                <select
                    :value="filters.status ?? ''"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white"
                    @change="(e) => applyFilter('status', (e.target as HTMLSelectElement).value)"
                >
                    <option v-for="opt in statuses" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </label>
            <label class="flex items-center gap-1.5 text-[11px] text-zinc-500">
                Category
                <select
                    :value="filters.category ?? ''"
                    class="text-xs border border-zinc-200 rounded px-2 py-1 bg-white"
                    @change="(e) => applyFilter('category', (e.target as HTMLSelectElement).value)"
                >
                    <option v-for="opt in categories" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
            </label>
        </div>
    </div>

    <div class="flex-1 px-8 py-6 overflow-y-auto">
        <div v-if="isEmpty" class="bg-white rounded-xl border border-zinc-200">
            <EmptyState
                icon="✅"
                title="No tasks"
                description="Tasks extracted from school messages will appear here."
            />
        </div>
        <div v-else class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
            <div class="grid grid-cols-[72px_1fr_110px_110px_120px_110px_64px] gap-4 px-5 py-2.5 bg-zinc-50 border-b border-zinc-200">
                <span
                    v-for="(h, i) in headers"
                    :key="h"
                    :class="[
                        'text-[11px] font-semibold uppercase tracking-wide text-zinc-400',
                        i === headers.length - 1 ? 'text-right' : '',
                    ]"
                >
                    {{ h }}
                </span>
            </div>
            <TaskRow
                v-for="(task, i) in tasks.data"
                :key="task.id"
                :task="task"
                :href="admin.tasks.show(task.id).url"
                :is-last="i === tasks.data.length - 1"
            />
        </div>

        <Pagination v-if="!isEmpty" :paginator="tasks" />
    </div>
</template>
