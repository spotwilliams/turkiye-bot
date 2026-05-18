<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface Crumb {
    label: string;
    href?: string;
}

defineProps<{ items: Crumb[] }>();
</script>

<template>
    <nav class="flex items-center gap-1.5 text-xs text-zinc-400 mb-1">
        <template v-for="(item, i) in items" :key="i">
            <span v-if="i > 0" class="text-zinc-300 select-none">/</span>
            <Link
                v-if="item.href"
                :href="item.href"
                class="hover:text-indigo-600 transition-colors"
            >
                {{ item.label }}
            </Link>
            <span v-else class="text-zinc-700 font-medium">{{ item.label }}</span>
        </template>
    </nav>
</template>
