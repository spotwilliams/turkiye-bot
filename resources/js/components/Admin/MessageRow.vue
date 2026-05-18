<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { fmtIstanbul, truncate } from '@/composables/useIstanbulDate';
import type { AdminMessageRow } from '@/types/admin';
import ProcessedBadge from './ProcessedBadge.vue';
import TaskCountBadge from './TaskCountBadge.vue';

const props = defineProps<{ msg: AdminMessageRow; href: string; isLast: boolean }>();

const display = computed(() => truncate(props.msg.summary || props.msg.original_text, 72));
const created = computed(() => fmtIstanbul(props.msg.created_at));
</script>

<template>
    <Link
        :href="href"
        :class="[
            'grid grid-cols-[64px_160px_1fr_72px_160px_140px] gap-4 items-center px-5 py-3 cursor-pointer transition-colors hover:bg-zinc-50 bg-white',
            !isLast && 'border-b border-zinc-100',
        ]"
    >
        <span class="font-mono text-[11px] text-zinc-400">{{ msg.ref }}</span>
        <span class="font-mono text-[11px] text-zinc-500 truncate" :title="msg.telegram_chat_id">
            {{ msg.telegram_chat_id }}
        </span>
        <span class="text-sm text-zinc-700 truncate">
            {{ display }}
            <span v-if="!msg.summary" class="ml-1.5 text-[10px] text-zinc-400 italic">turkish</span>
        </span>
        <div class="flex justify-start">
            <TaskCountBadge :count="msg.tasks_count" />
        </div>
        <div>
            <ProcessedBadge :processed-at="msg.processed_at" />
        </div>
        <span class="font-mono text-[11px] text-zinc-400">{{ created }}</span>
    </Link>
</template>
