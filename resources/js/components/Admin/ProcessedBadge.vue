<script setup lang="ts">
import { computed } from 'vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import type { MessageStatus } from '@/types/admin';

const props = defineProps<{
    processedAt: string | null;
    failedAt?: string | null;
    status?: MessageStatus;
}>();

const formatted = computed(() => fmtIstanbul(props.processedAt ?? props.failedAt));

const resolvedStatus = computed<MessageStatus>(() => {
    if (props.status) {
        return props.status;
    }
    if (props.failedAt) {
        return 'failed';
    }
    return props.processedAt ? 'processed' : 'pending';
});

const styles: Record<MessageStatus, string> = {
    pending: 'bg-amber-50 text-amber-700 ring-1 ring-inset ring-amber-600/25',
    processed: 'bg-green-50 text-green-700 ring-1 ring-inset ring-green-600/25',
    failed: 'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/30',
};
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <span :class="['inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium tracking-wide uppercase', styles[resolvedStatus]]">
            {{ resolvedStatus }}
        </span>
        <span v-if="formatted" class="font-mono text-xs text-zinc-500">{{ formatted }}</span>
    </span>
</template>
