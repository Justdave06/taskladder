<script setup lang="ts">
import { FileText, Archive } from 'lucide-vue-next';

const props = defineProps<{
    adminDocs: any[];
}>();

const completedDocs = props.adminDocs.filter(d => d.status === 'completed' || d.status === 'rejected' || d.status === 'ready');

const statusStyle: Record<string, string> = {
    ready: 'bg-[#EEEDFE] text-[#26215C]',
    completed: 'bg-[#EAF3DE] text-[#27500A]',
    rejected: 'bg-[#FCEBEB] text-[#791F1F]',
};

const statusLabel: Record<string, string> = {
    ready: 'Ready',
    completed: 'Completed',
    rejected: 'Rejected',
};
</script>

<template>
    <div class="flex flex-col gap-4">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Records</h2>

        <div v-if="completedDocs.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center dark:border-gray-600 dark:bg-gray-800/60">
            <Archive class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-500" />
            <p class="mt-2 text-sm text-gray-500">No completed records yet.</p>
        </div>

        <div v-for="doc in completedDocs" :key="doc.id" class="rounded-xl border border-gray-200/60 bg-white px-4 py-3 dark:border-gray-700/40 dark:bg-gray-800/60">
            <div class="flex items-center gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-gray-100">
                    <FileText class="h-4 w-4 text-gray-500" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ doc.title }}</div>
                    <div class="text-xs text-gray-500">{{ doc.reference_number }} · {{ doc.requester_name }} · {{ doc.department_from?.name }}</div>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="statusStyle[doc.status] || 'bg-gray-100 text-gray-600'">{{ statusLabel[doc.status] || doc.status }}</span>
                <span class="text-xs text-gray-400">{{ doc.ready_at?.slice(0, 10) || doc.completed_at?.slice(0, 10) || doc.updated_at?.slice(0, 10) }}</span>
            </div>
        </div>
    </div>
</template>
