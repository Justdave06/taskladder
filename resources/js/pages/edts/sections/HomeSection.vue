<script setup lang="ts">
import { FileText, FilePlus, Search, ArrowRight, Clock } from 'lucide-vue-next';

const emit = defineEmits<{
    navigate: [section: string];
}>();

const props = defineProps<{
    documents: any[];
}>();

const statusStyle: Record<string, string> = {
    pending: 'bg-[#FAEEDA] text-[#633806]',
    review: 'bg-[#FAEEDA] text-[#633806]',
    receive: 'bg-[#E6F1FB] text-[#0C447C]',
    in_transit: 'bg-[#E6F1FB] text-[#0C447C]',
    ready: 'bg-[#EEEDFE] text-[#26215C]',
    completed: 'bg-[#EAF3DE] text-[#27500A]',
    rejected: 'bg-[#FCEBEB] text-[#791F1F]',
};

const statusLabel: Record<string, string> = {
    pending: 'Pending',
    review: 'Under review',
    receive: 'Received',
    in_transit: 'In transit',
    ready: 'Ready',
    completed: 'Completed',
    rejected: 'Rejected',
};
</script>

<template>
    <div class="flex flex-col gap-6">
        <div class="flex flex-col items-center justify-center rounded-xl bg-gradient-to-br from-[#f7fbff] to-[#E6F1FB] px-6 py-12 text-center">
            <div class="mb-2 rounded-full bg-[#185FA5] p-3">
                <FileText class="h-8 w-8 text-white" />
            </div>
            <h1 class="text-2xl font-semibold text-[#0C447C]">Document Request Portal</h1>
            <p class="mt-1 text-sm text-gray-500">Easy way to request and track your documents</p>
            <div class="mt-6 flex gap-3">
                <button class="flex items-center gap-2 rounded-xl bg-[#185FA5] px-5 py-2.5 text-sm font-medium text-white transition hover:bg-[#0C447C]" @click="emit('navigate', 'request')">
                    <FilePlus class="h-4 w-4" />
                    Request document
                    <ArrowRight class="h-4 w-4" />
                </button>
                <button class="flex items-center gap-2 rounded-xl border border-[#185FA5] px-5 py-2.5 text-sm font-medium text-[#185FA5] transition hover:bg-[#E6F1FB]" @click="emit('navigate', 'track')">
                    <Search class="h-4 w-4" />
                    Track request
                </button>
            </div>
        </div>

        <div v-if="documents.length > 0">
            <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Your Recent Requests</h2>
            <div class="flex flex-col gap-2">
                <div
                    v-for="doc in documents.slice(0, 5)"
                    :key="doc.id"
                    class="flex items-center gap-3 rounded-xl border border-gray-200/60 bg-white px-4 py-3 dark:border-gray-700/40 dark:bg-gray-800/60"
                >
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#E6F1FB]">
                        <FileText class="h-4 w-4 text-[#185FA5]" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ doc.title }}</div>
                        <div class="text-xs text-gray-500">{{ doc.reference_number }}</div>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="statusStyle[doc.status] || 'bg-gray-100 text-gray-600'">{{ statusLabel[doc.status] || doc.status }}</span>
                    <button class="shrink-0 text-xs font-medium text-[#185FA5] hover:underline" @click="emit('navigate', 'my-requests')">
                        View
                    </button>
                </div>
            </div>
        </div>

        <div v-else class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center dark:border-gray-600 dark:bg-gray-800/60">
            <Clock class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-500" />
            <p class="mt-2 text-sm text-gray-500">No document requests yet.</p>
            <button class="mt-3 text-sm font-medium text-[#185FA5] hover:underline" @click="emit('navigate', 'request')">
                Submit your first request
            </button>
        </div>
    </div>
</template>
