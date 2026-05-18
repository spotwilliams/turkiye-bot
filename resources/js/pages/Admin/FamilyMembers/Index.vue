<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import EmptyState from '@/components/Admin/EmptyState.vue';
import Pagination from '@/components/Admin/Pagination.vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import admin from '@/routes/admin';
import type { AdminFamilyMemberRow, Paginator } from '@/types/admin';

const props = defineProps<{ members: Paginator<AdminFamilyMemberRow> }>();

const headers = ['ID', 'Name', 'Role', 'Telegram User', 'Chat', 'Timezone', 'Created'];
const isEmpty = computed(() => props.members.data.length === 0);
</script>

<template>
    <Head title="Family Members" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb :items="[{ label: 'Admin' }, { label: 'Family Members' }]" />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">Family Members</h1>
            <span class="text-xs text-zinc-400 font-mono">
                {{ members.total === 0 ? '0 members' : `${members.total} total` }}
            </span>
        </div>
    </div>

    <div class="flex-1 px-8 py-6 overflow-y-auto">
        <div v-if="isEmpty" class="bg-white rounded-xl border border-zinc-200">
            <EmptyState icon="👪" title="No family members" description="Registered Telegram parents will appear here." />
        </div>
        <div v-else class="bg-white rounded-xl border border-zinc-200 overflow-hidden">
            <div class="grid grid-cols-[72px_1fr_100px_140px_140px_140px_140px] gap-4 px-5 py-2.5 bg-zinc-50 border-b border-zinc-200">
                <span
                    v-for="h in headers"
                    :key="h"
                    class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400"
                >
                    {{ h }}
                </span>
            </div>
            <Link
                v-for="(m, i) in members.data"
                :key="m.id"
                :href="admin.familyMembers.show(m.id).url"
                :class="[
                    'grid grid-cols-[72px_1fr_100px_140px_140px_140px_140px] gap-4 items-center px-5 py-3 cursor-pointer transition-colors hover:bg-zinc-50 bg-white',
                    i !== members.data.length - 1 && 'border-b border-zinc-100',
                ]"
            >
                <span class="font-mono text-[11px] text-zinc-400">{{ m.ref }}</span>
                <span class="text-sm text-zinc-700 truncate">{{ m.name }}</span>
                <span class="text-[11px] text-zinc-500 capitalize">{{ m.role }}</span>
                <span class="font-mono text-[11px] text-zinc-500 truncate">{{ m.telegram_user_id }}</span>
                <span class="font-mono text-[11px] text-zinc-500 truncate">{{ m.telegram_chat_id }}</span>
                <span class="font-mono text-[11px] text-zinc-500 truncate">{{ m.timezone }}</span>
                <span class="font-mono text-[11px] text-zinc-400">{{ fmtIstanbul(m.created_at) }}</span>
            </Link>
        </div>

        <Pagination v-if="!isEmpty" :paginator="members" />
    </div>
</template>
