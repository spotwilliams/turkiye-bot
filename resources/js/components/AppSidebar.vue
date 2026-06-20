<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import admin from '@/routes/admin';

interface NavItem {
    id: string;
    label: string;
    icon: string;
    href?: string;
    enabled: boolean;
}

const page = usePage();

const navItems = computed<NavItem[]>(() => [
    { id: 'messages', label: 'Messages', icon: '📨', href: admin.messages.index().url, enabled: true },
    { id: 'tasks', label: 'Tasks', icon: '✅', href: admin.tasks.index().url, enabled: true },
    { id: 'reminders', label: 'Reminders', icon: '🔔', href: admin.reminders.index().url, enabled: true },
    { id: 'families', label: 'Family Members', icon: '👨‍👩‍👧', href: admin.familyMembers.index().url, enabled: true },
]);

function isActive(item: NavItem): boolean {
    if (!item.href) {
return false;
}

    return page.url.startsWith(item.href);
}
</script>

<template>
    <aside class="w-56 shrink-0 bg-white border-r border-zinc-200 flex flex-col h-screen sticky top-0">
        <div class="h-14 flex items-center gap-2.5 px-4 border-b border-zinc-100">
            <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center shrink-0">
                <svg width="14" height="14" viewBox="0 0 14 14" fill="none">
                    <path d="M7 1L12 4V10L7 13L2 10V4L7 1Z" stroke="white" stroke-width="1.4" stroke-linejoin="round" />
                    <circle cx="7" cy="7" r="2" fill="white" />
                </svg>
            </div>
            <div>
                <div class="text-xs font-semibold text-zinc-900 leading-tight">Türkiye Bot</div>
                <div class="text-[10px] text-zinc-400 leading-tight">Admin Panel</div>
            </div>
        </div>

        <nav class="flex-1 px-2 py-3 space-y-0.5 overflow-y-auto">
            <div class="px-2 pb-1.5 pt-0.5">
                <span class="text-[10px] font-semibold uppercase tracking-widest text-zinc-400">Main</span>
            </div>
            <template v-for="item in navItems" :key="item.id">
                <Link
                    v-if="item.enabled && item.href"
                    :href="item.href"
                    :class="[
                        'w-full flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-left transition-colors text-sm',
                        isActive(item)
                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                            : 'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-800',
                    ]"
                >
                    <span class="text-[15px] leading-none shrink-0">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                </Link>
                <div
                    v-else
                    class="w-full flex items-center gap-2.5 px-2.5 py-1.5 rounded-md text-sm text-zinc-300 cursor-not-allowed"
                >
                    <span class="text-[15px] leading-none shrink-0 grayscale opacity-50">{{ item.icon }}</span>
                    <span>{{ item.label }}</span>
                    <span class="ml-auto text-[9px] uppercase tracking-widest text-zinc-300 font-medium">Soon</span>
                </div>
            </template>
        </nav>

        <div class="px-3 py-3 border-t border-zinc-100">
            <div class="flex items-center gap-2">
                <div class="w-6 h-6 rounded-full bg-zinc-200 flex items-center justify-center text-[10px] font-semibold text-zinc-600">
                    O
                </div>
                <div class="min-w-0">
                    <div class="text-xs font-medium text-zinc-700 truncate">Operator</div>
                    <div class="text-[10px] text-zinc-400">Read-only · v1</div>
                </div>
            </div>
        </div>
    </aside>
</template>
