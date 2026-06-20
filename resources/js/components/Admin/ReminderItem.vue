<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import WebRemindersController from '@/actions/App/Http/Controllers/Web/WebRemindersController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import type { AdminReminder } from '@/types/admin';
import ReminderTypePill from './ReminderTypePill.vue';

const props = withDefaults(defineProps<{ reminder: AdminReminder; editable?: boolean }>(), {
    editable: false,
});

const scheduled = computed(() => fmtIstanbul(props.reminder.scheduled_at));
const sentTime = computed(() => fmtIstanbul(props.reminder.sent_at, 'time'));

// datetime-local wants 'YYYY-MM-DDThh:mm' in local time.
function toLocalInput(date: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

const snoozeAt = ref(toLocalInput(new Date(Date.now() + 60 * 60 * 1000)));
const minLocal = computed(() => toLocalInput(new Date()));

function preset(kind: 'hour' | 'tonight' | 'tomorrow'): void {
    const d = new Date();

    if (kind === 'hour') {
        d.setHours(d.getHours() + 1);
    } else if (kind === 'tonight') {
        d.setHours(20, 0, 0, 0);
    } else {
        d.setDate(d.getDate() + 1);
        d.setHours(7, 30, 0, 0);
    }

    snoozeAt.value = toLocalInput(d);
}
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

        <div v-if="editable" class="shrink-0">
            <Dialog>
                <DialogTrigger as-child>
                    <Button size="sm" variant="ghost" class="h-6 px-2 text-[11px]">⏰ Snooze</Button>
                </DialogTrigger>
                <DialogContent>
                    <Form
                        v-bind="WebRemindersController.snooze.form(reminder.id)"
                        :options="{ preserveScroll: true }"
                        v-slot="{ processing }"
                    >
                        <DialogHeader class="space-y-2">
                            <DialogTitle>Snooze reminder</DialogTitle>
                            <DialogDescription>Push this nudge to a later time. It fires again then.</DialogDescription>
                        </DialogHeader>
                        <div class="py-4 space-y-3">
                            <div class="flex gap-2">
                                <Button type="button" size="sm" variant="outline" @click="preset('hour')">+1 hour</Button>
                                <Button type="button" size="sm" variant="outline" @click="preset('tonight')">Tonight</Button>
                                <Button type="button" size="sm" variant="outline" @click="preset('tomorrow')">Tomorrow AM</Button>
                            </div>
                            <Input v-model="snoozeAt" type="datetime-local" name="scheduled_at" :min="minLocal" required />
                        </div>
                        <DialogFooter class="gap-2">
                            <DialogClose as-child>
                                <Button variant="secondary" type="button">Cancel</Button>
                            </DialogClose>
                            <Button type="submit" :disabled="processing">Snooze</Button>
                        </DialogFooter>
                    </Form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
