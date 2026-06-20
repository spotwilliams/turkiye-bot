<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed } from 'vue';
import WebTasksController from '@/actions/App/Http/Controllers/Web/WebTasksController';
import InputError from '@/components/InputError.vue';
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
import { Label } from '@/components/ui/label';
import type { AdminTaskDetail, TaskCategory } from '@/types/admin';

const props = defineProps<{ task: AdminTaskDetail }>();

const isOpenForChanges = computed(() => props.task.status === 'pending');
const isCompleted = computed(() => props.task.status === 'completed');

const categories: Array<{ value: TaskCategory; label: string }> = [
    { value: 'money', label: 'Money' },
    { value: 'homework', label: 'Homework' },
    { value: 'item', label: 'Item' },
    { value: 'event', label: 'Event' },
    { value: 'other', label: 'Other' },
];
</script>

<template>
    <div class="flex items-center gap-2">
        <!-- Done -->
        <Form
            v-bind="WebTasksController.complete.form(task.id)"
            :options="{ preserveScroll: true }"
            v-slot="{ processing }"
        >
            <Button type="submit" size="sm" variant="outline" :disabled="processing || isCompleted">
                ✅ {{ isCompleted ? 'Done' : 'Mark done' }}
            </Button>
        </Form>

        <!-- Reschedule -->
        <Dialog>
            <DialogTrigger as-child>
                <Button size="sm" variant="outline" :disabled="!isOpenForChanges">📅 Reschedule</Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    v-bind="WebTasksController.reschedule.form(task.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                >
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Reschedule task</DialogTitle>
                        <DialogDescription>Pick a new due date. Reminders are recreated for the new date.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-3 py-4">
                        <div class="grid gap-2">
                            <Label for="rs-date">Due date</Label>
                            <Input id="rs-date" type="date" name="due_date" :default-value="task.due_date ?? ''" required />
                            <InputError :message="errors.due_date" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="rs-time">Due time (optional)</Label>
                            <Input id="rs-time" type="time" name="due_time" :default-value="task.due_time ?? ''" />
                            <InputError :message="errors.due_time" />
                        </div>
                    </div>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="processing">Save</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Edit -->
        <Dialog>
            <DialogTrigger as-child>
                <Button size="sm" variant="outline" :disabled="!isOpenForChanges">✏️ Edit</Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    v-bind="WebTasksController.update.form(task.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ errors, processing }"
                >
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Edit task</DialogTitle>
                        <DialogDescription>Changing the category or due date recreates reminders.</DialogDescription>
                    </DialogHeader>
                    <div class="grid gap-3 py-4">
                        <div class="grid gap-2">
                            <Label for="ed-desc">Description</Label>
                            <Input id="ed-desc" name="description" :default-value="task.description" />
                            <InputError :message="errors.description" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="ed-cat">Category</Label>
                            <select
                                id="ed-cat"
                                name="category"
                                class="text-sm border border-zinc-200 rounded-md px-2.5 py-2 bg-white"
                                :value="task.category"
                            >
                                <option v-for="c in categories" :key="c.value" :value="c.value">{{ c.label }}</option>
                            </select>
                            <InputError :message="errors.category" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="grid gap-2">
                                <Label for="ed-amount">Amount</Label>
                                <Input id="ed-amount" name="amount" type="number" step="0.01" :default-value="task.amount ?? ''" />
                                <InputError :message="errors.amount" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="ed-currency">Currency</Label>
                                <Input id="ed-currency" name="currency" maxlength="3" :default-value="task.currency ?? ''" />
                                <InputError :message="errors.currency" />
                            </div>
                        </div>
                    </div>
                    <DialogFooter class="gap-2">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">Cancel</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="processing">Save</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>

        <!-- Delete (soft cancel) -->
        <Dialog>
            <DialogTrigger as-child>
                <Button size="sm" variant="destructive" :disabled="task.status === 'cancelled'">🗑 Delete</Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    v-bind="WebTasksController.destroy.form(task.id)"
                    :options="{ preserveScroll: true }"
                    v-slot="{ processing }"
                >
                    <DialogHeader class="space-y-2">
                        <DialogTitle>Delete this task?</DialogTitle>
                        <DialogDescription>
                            The task is cancelled and its pending reminders stop. The record is kept for history.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter class="gap-2 mt-4">
                        <DialogClose as-child>
                            <Button variant="secondary" type="button">Keep</Button>
                        </DialogClose>
                        <Button type="submit" variant="destructive" :disabled="processing">Delete</Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
