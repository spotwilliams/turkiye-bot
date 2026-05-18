<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import type { Paginator } from '@/types/admin';

defineProps<{ paginator: Paginator<unknown> }>();

function isNav(label: string): 'prev' | 'next' | null {
    if (label.includes('Previous') || label.includes('&laquo;')) {
return 'prev';
}

    if (label.includes('Next') || label.includes('&raquo;')) {
return 'next';
}

    return null;
}

function display(label: string): string {
    const nav = isNav(label);

    if (nav === 'prev') {
return '←';
}

    if (nav === 'next') {
return '→';
}

    return label;
}
</script>

<template>
    <div class="flex items-center justify-between mt-4">
        <span class="text-xs text-zinc-400">
            Showing {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} of {{ paginator.total }}
        </span>
        <div class="flex items-center gap-1">
            <template v-for="(link, i) in paginator.links" :key="i">
                <Link
                    v-if="link.url"
                    :href="link.url"
                    preserve-scroll
                    :class="[
                        'w-7 h-7 flex items-center justify-center rounded text-xs font-medium transition-colors',
                        link.active ? 'bg-indigo-600 text-white' : 'text-zinc-600 hover:bg-zinc-100',
                    ]"
                >
                    {{ display(link.label) }}
                </Link>
                <span
                    v-else
                    class="w-7 h-7 flex items-center justify-center rounded text-xs font-medium text-zinc-300 cursor-not-allowed"
                >
                    {{ display(link.label) }}
                </span>
            </template>
        </div>
    </div>
</template>
