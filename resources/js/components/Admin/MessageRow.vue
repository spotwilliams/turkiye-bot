<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WebMessagesController from '@/actions/App/Http/Controllers/Web/WebMessagesController';
import { fmtIstanbul, truncate } from '@/composables/useIstanbulDate';
import type { AdminMessageRow } from '@/types/admin';
import ProcessedBadge from './ProcessedBadge.vue';
import TaskCountBadge from './TaskCountBadge.vue';

const props = defineProps<{ msg: AdminMessageRow; href: string; isLast: boolean }>();

const display = computed(() => truncate(props.msg.summary || props.msg.original_text, 72));
const created = computed(() => fmtIstanbul(props.msg.created_at));

// The row is a navigation Link, so the retry control submits via the router
// with propagation stopped instead of nesting a <form> inside the anchor.
const retrying = ref(false);

function retry(): void {
    router.post(
        WebMessagesController.retry.url(props.msg.id),
        {},
        {
            preserveScroll: true,
            onStart: () => {
                retrying.value = true;
            },
            onFinish: () => {
                retrying.value = false;
            },
        },
    );
}
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
        <div class="flex items-center gap-2">
            <ProcessedBadge :status="msg.status" :processed-at="msg.processed_at" :failed-at="msg.failed_at" />
            <button
                v-if="msg.status === 'failed'"
                type="button"
                :disabled="retrying"
                class="text-[11px] font-medium text-indigo-600 hover:text-indigo-800 disabled:opacity-50"
                @click.stop.prevent="retry"
            >
                ↻ Retry
            </button>
        </div>
        <span class="font-mono text-[11px] text-zinc-400">{{ created }}</span>
    </Link>
</template>
