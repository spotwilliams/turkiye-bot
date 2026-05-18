<script setup lang="ts">
import { computed } from 'vue';
import { fmtIstanbul } from '@/composables/useIstanbulDate';
import StatusPill from './StatusPill.vue';

const props = defineProps<{ processedAt: string | null }>();

const formatted = computed(() => fmtIstanbul(props.processedAt));
</script>

<template>
    <StatusPill v-if="!processedAt" status="pending" />
    <span v-else class="font-mono text-xs text-zinc-500">{{ formatted }}</span>
</template>
