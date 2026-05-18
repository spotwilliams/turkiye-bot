<script setup lang="ts">
import { computed } from 'vue';
import type { Assignee } from '@/types/admin';

const props = defineProps<{ assignedTo: Assignee }>();

const label = computed(() => {
    if (!props.assignedTo) {
return null;
}

    return props.assignedTo.charAt(0).toUpperCase() + props.assignedTo.slice(1);
});
</script>

<template>
    <span v-if="!assignedTo || assignedTo === 'unassigned'" class="text-xs text-zinc-400">—</span>
    <span
        v-else
        class="inline-flex items-center px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 text-[11px] font-medium ring-1 ring-inset ring-zinc-200"
    >
        {{ label }}
    </span>
</template>
