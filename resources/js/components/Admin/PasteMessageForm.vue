<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import WebMessagesController from '@/actions/App/Http/Controllers/Web/WebMessagesController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';

const open = ref(false);
const text = ref('');
</script>

<template>
    <div class="bg-white rounded-xl border border-zinc-200 mb-5">
        <button
            type="button"
            class="w-full flex items-center justify-between px-5 py-3 text-sm font-medium text-zinc-700 hover:bg-zinc-50 transition-colors"
            @click="open = !open"
        >
            <span class="flex items-center gap-2">
                <span>📨</span>
                Paste a school message
            </span>
            <span
                class="text-zinc-300 inline-block transition-transform"
                :style="{ transform: open ? 'rotate(180deg)' : 'rotate(0deg)' }"
            >▾</span>
        </button>

        <div v-if="open" class="border-t border-zinc-100 px-5 py-4">
            <Form
                v-bind="WebMessagesController.store.form()"
                :options="{ preserveScroll: true }"
                @success="text = ''"
                v-slot="{ errors, processing }"
            >
                <p class="text-xs text-zinc-500 mb-2">
                    Paste the Turkish message from school. It is translated, summarised, and turned
                    into tasks with reminders — the same pipeline as Telegram.
                </p>
                <textarea
                    v-model="text"
                    name="text"
                    rows="4"
                    placeholder="Değerli veliler, Cuma günü gezi için 350 TL getirilmesi gerekmektedir…"
                    class="w-full text-sm border border-zinc-200 rounded-md px-3 py-2 bg-white resize-y focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-indigo-200"
                    :disabled="processing"
                ></textarea>
                <InputError :message="errors.text" class="mt-1" />
                <div class="flex justify-end mt-3">
                    <Button type="submit" size="sm" :disabled="processing || text.trim() === ''">
                        {{ processing ? 'Sending…' : 'Process message' }}
                    </Button>
                </div>
            </Form>
        </div>
    </div>
</template>
