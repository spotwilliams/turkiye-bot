<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminBreadcrumb from '@/components/Admin/AdminBreadcrumb.vue';
import MetaField from '@/components/Admin/MetaField.vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import admin from '@/routes/admin';
import type { AdminFamilyMemberDetail } from '@/types/admin';

const props = defineProps<{ member: AdminFamilyMemberDetail }>();

const created = computed(() => fmtIstanbul(props.member.created_at) ?? '');
const updated = computed(() => fmtIstanbul(props.member.updated_at) ?? '');
const prefsJson = computed(() =>
    props.member.preferences ? JSON.stringify(props.member.preferences, null, 2) : null,
);
</script>

<template>
    <Head :title="member.name" />
    <div class="px-8 pt-7 pb-5 border-b border-zinc-100 bg-white">
        <AdminBreadcrumb
            :items="[
                { label: 'Family Members', href: admin.familyMembers.index().url },
                { label: member.name },
            ]"
        />
        <div class="flex items-center justify-between mt-1">
            <h1 class="text-lg font-semibold text-zinc-900">{{ member.name }}</h1>
            <span class="font-mono text-[11px] text-zinc-400">{{ member.ref }}</span>
        </div>
        <div class="flex flex-wrap items-center gap-x-6 gap-y-1 mt-3">
            <MetaField label="Role" :value="member.role" />
            <MetaField label="Timezone" :value="member.timezone" mono />
            <MetaField label="Created" :value="created" mono />
            <MetaField label="Updated" :value="updated" mono />
        </div>
    </div>

    <div class="flex-1 overflow-y-auto px-8 py-6 space-y-4">
        <div class="bg-white rounded-xl border border-zinc-200 p-4 space-y-2">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400">Telegram</div>
            <div class="grid grid-cols-2 gap-3">
                <MetaField label="User ID" :value="member.telegram_user_id" mono />
                <MetaField label="Chat ID" :value="member.telegram_chat_id" mono />
            </div>
        </div>

        <div v-if="prefsJson" class="bg-white rounded-xl border border-zinc-200 p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-zinc-400 mb-2">
                Preferences
            </div>
            <pre class="text-[11px] font-mono text-zinc-700 bg-zinc-50 rounded p-3 overflow-x-auto">{{ prefsJson }}</pre>
        </div>
    </div>
</template>
