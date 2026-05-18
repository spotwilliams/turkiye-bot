<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="flex min-h-screen bg-zinc-100" style="font-family: 'IBM Plex Sans', system-ui, sans-serif;">
        <AppSidebar />
        <main class="flex-1 flex flex-col min-w-0">
            <slot />
        </main>
        <Toaster />
    </div>
</template>
