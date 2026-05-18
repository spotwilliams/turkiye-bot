<script setup lang="ts">
import { computed } from 'vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import type { AdminReminder } from '@/types/admin';
import ReminderTypePill from './ReminderTypePill.vue';

const props = defineProps<{ reminder: AdminReminder }>();

const scheduled = computed(() => fmtIstanbul(props.reminder.scheduled_at));
const sentTime = computed(() => fmtIstanbul(props.reminder.sent_at, 'time'));
</script>

<template>
    <div class="px-4 py-2.5 flex gap-3">
        <div class="flex flex-col items-center pt-0.5">
            <div :class="['w-1.5 h-1.5 rounded-full mt-1 shrink-0', reminder.sent ? 'bg-green-400' : 'bg-zinc-300']"></div>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 mb-1 flex-wrap">
                <span class="font-mono text-[10px] text-zinc-400">{{ scheduled }}</span>
                <ReminderTypePill :type="reminder.type" />
                <span v-if="reminder.sent" class="text-[10px] text-green-600 font-medium">
                    Sent {{ sentTime }}
                </span>
            </div>
            <p class="text-[11px] text-zinc-500 leading-relaxed">{{ reminder.text }}</p>
        </div>
    </div>
</template>
