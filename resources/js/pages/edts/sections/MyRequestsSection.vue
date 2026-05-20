<script setup lang="ts">
import { ref } from 'vue';
import { FileText, ChevronDown, Clock, User, Calendar, Copy, AlertTriangle, Minus, ArrowDown } from 'lucide-vue-next';

const props = defineProps<{
    documents: any[];
}>();

const openCard = ref<string | null>(null);

function toggleCard(id: string) {
    openCard.value = openCard.value === id ? null : id;
}

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

function copyRef(val: string) {
    navigator.clipboard?.writeText(val);
}
</script>

<template>
    <div class="flex flex-col gap-3">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">My Requests</h2>

        <div v-if="documents.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center dark:border-gray-600 dark:bg-gray-800/60">
            <FileText class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-500" />
            <p class="mt-2 text-sm text-gray-500">No document requests yet.</p>
        </div>

        <div v-for="doc in documents" :key="doc.id" class="overflow-hidden rounded-xl border border-gray-200/60 bg-white dark:border-gray-700/40 dark:bg-gray-800/60">
            <div class="flex cursor-pointer items-center gap-3 px-4 py-3" @click="toggleCard(String(doc.id))">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[#E6F1FB]">
                    <FileText class="h-5 w-5 text-[#185FA5]" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ doc.title }}</span>
                        <span v-if="doc.priority === 'high'" class="flex items-center gap-0.5 rounded-full bg-red-100 px-1.5 py-0.5 text-[9px] font-medium text-red-700"><AlertTriangle class="h-2.5 w-2.5" /> High</span>
                        <span v-else-if="doc.priority === 'medium'" class="flex items-center gap-0.5 rounded-full bg-amber-100 px-1.5 py-0.5 text-[9px] font-medium text-amber-700"><Minus class="h-2.5 w-2.5" /> Med</span>
                        <span v-else-if="doc.priority === 'low'" class="flex items-center gap-0.5 rounded-full bg-green-100 px-1.5 py-0.5 text-[9px] font-medium text-green-700"><ArrowDown class="h-2.5 w-2.5" /> Low</span>
                    </div>
                    <div class="mt-0.5 flex flex-wrap items-center gap-2 text-[11px] text-gray-500">
                        <span class="flex items-center gap-1"><FileText class="h-3 w-3" /> {{ doc.reference_number }}</span>
                        <span class="flex items-center gap-1"><Calendar class="h-3 w-3" /> {{ doc.created_at?.slice(0, 10) }}</span>
                    </div>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="statusStyle[doc.status] || 'bg-gray-100 text-gray-600'">{{ statusLabel[doc.status] || doc.status }}</span>
                <ChevronDown class="h-4 w-4 shrink-0 text-gray-400 transition" :class="openCard === String(doc.id) ? 'rotate-180' : ''" />
            </div>

            <div v-if="openCard === String(doc.id)" class="border-t border-gray-100 px-4 pb-4 pt-2 dark:border-gray-700/40">
                <div class="mb-3 flex items-center gap-2 rounded-lg bg-gray-50 p-2.5 text-xs dark:bg-gray-700/30">
                    <span class="text-gray-500">Ref:</span>
                    <span class="font-mono font-medium text-gray-900 dark:text-gray-100">{{ doc.reference_number }}</span>
                    <div class="flex-1" />
                    <button class="flex items-center gap-1 rounded-md border border-gray-200 px-2 py-1 text-[11px] transition hover:border-[#378ADD] hover:text-[#185FA5]" @click.stop="copyRef(doc.reference_number)"><Copy class="h-3 w-3" /> Copy</button>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div><span class="text-gray-400">Requester</span><p class="font-medium">{{ doc.requester_name }}</p></div>
                    <div><span class="text-gray-400">Department</span><p class="font-medium">{{ doc.department_from?.name }}</p></div>
                    <div><span class="text-gray-400">Email</span><p class="font-medium">{{ doc.requester_email }}</p></div>
                    <div><span class="text-gray-400">Purpose</span><p class="font-medium">{{ doc.purpose }}</p></div>
                </div>
                <div v-if="doc.doc_types?.length" class="mt-2 flex flex-wrap gap-1">
                    <span v-for="dt in doc.doc_types" :key="dt.id" class="rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[10px] text-[#0C447C]">{{ dt.name }}</span>
                </div>
                <div v-if="doc.notes" class="mt-2 rounded-lg bg-gray-50 p-2 text-xs text-gray-600 dark:bg-gray-700/30 dark:text-gray-300">{{ doc.notes }}</div>
            </div>
        </div>
    </div>
</template>
