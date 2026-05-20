<script setup lang="ts">
import { FileText, Clock, CheckCircle, Truck, Users, Eye, RefreshCw, AlertTriangle, Minus, ArrowDown } from 'lucide-vue-next';

const props = defineProps<{
    metrics: { total: number; pending: number; review: number; receive: number; in_transit: number; ready: number; completed: number; rejected: number; requestees: number; high: number; medium: number; low: number };
    adminDocs: any[];
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
    in_transit: 'Processing',
    ready: 'Ready',
    completed: 'Completed',
    rejected: 'Rejected',
};
</script>

<template>
    <div class="flex flex-col gap-5">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Admin Dashboard</h2>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
            <div class="rounded-xl border border-[#B5D4F4] bg-[#f7fbff] p-3">
                <FileText class="h-5 w-5 text-[#185FA5]" />
                <div class="mt-1.5 text-2xl font-semibold text-[#185FA5]">{{ metrics.total }}</div>
                <div class="text-[11px] text-gray-500">Total requests</div>
            </div>
            <div class="rounded-xl border border-[#B5D4F4] bg-[#f7fbff] p-3">
                <Users class="h-5 w-5 text-[#185FA5]" />
                <div class="mt-1.5 text-2xl font-semibold text-[#185FA5]">{{ metrics.requestees }}</div>
                <div class="text-[11px] text-gray-500">Requestees</div>
            </div>
            <div class="rounded-xl border border-[#FAEEDA] bg-[#FFFCF5] p-3">
                <Clock class="h-5 w-5 text-[#BA7517]" />
                <div class="mt-1.5 text-2xl font-semibold text-[#BA7517]">{{ metrics.pending + metrics.review }}</div>
                <div class="text-[11px] text-gray-500">Under review</div>
            </div>
            <div class="rounded-xl border border-[#EAF3DE] bg-[#FAFDF6] p-3">
                <CheckCircle class="h-5 w-5 text-[#1D9E75]" />
                <div class="mt-1.5 text-2xl font-semibold text-[#1D9E75]">{{ metrics.completed }}</div>
                <div class="text-[11px] text-gray-500">Completed</div>
            </div>
            <div class="rounded-xl border border-[#EEEDFE] bg-[#F8F7FF] p-3">
                <Eye class="h-5 w-5 text-[#26215C]" />
                <div class="mt-1.5 text-2xl font-semibold text-[#26215C]">{{ metrics.ready }}</div>
                <div class="text-[11px] text-gray-500">Ready</div>
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Priority Breakdown</h3>
            <div class="grid grid-cols-3 gap-3">
                <div class="rounded-xl border border-red-200 bg-red-50 p-3">
                    <div class="flex items-center gap-2">
                        <AlertTriangle class="h-4 w-4 text-red-600" />
                        <span class="text-xs font-medium text-red-700">High</span>
                    </div>
                    <div class="mt-1 text-xl font-semibold text-red-700">{{ metrics.high }}</div>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <div class="flex items-center gap-2">
                        <Minus class="h-4 w-4 text-amber-600" />
                        <span class="text-xs font-medium text-amber-700">Medium</span>
                    </div>
                    <div class="mt-1 text-xl font-semibold text-amber-700">{{ metrics.medium }}</div>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 p-3">
                    <div class="flex items-center gap-2">
                        <ArrowDown class="h-4 w-4 text-green-600" />
                        <span class="text-xs font-medium text-green-700">Low</span>
                    </div>
                    <div class="mt-1 text-xl font-semibold text-green-700">{{ metrics.low }}</div>
                </div>
            </div>
        </div>

        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Recent Requests</h3>
            <div v-if="adminDocs.length === 0" class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-8 text-center dark:border-gray-600 dark:bg-gray-800/60">
                <p class="text-sm text-gray-500">No requests yet.</p>
            </div>
            <div v-for="doc in adminDocs.slice(0, 10)" :key="doc.id" class="mb-2 flex items-center gap-3 rounded-xl border border-gray-200/60 bg-white px-4 py-3 dark:border-gray-700/40 dark:bg-gray-800/60">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#E6F1FB]">
                    <FileText class="h-4 w-4 text-[#185FA5]" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">{{ doc.title }}</div>
                    <div class="text-xs text-gray-500">{{ doc.reference_number }} · {{ doc.department_from?.name }}</div>
                </div>
                <span v-if="doc.priority === 'high'" class="flex items-center gap-1 rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-medium text-red-700"><AlertTriangle class="h-3 w-3" /> High</span>
                <span v-else-if="doc.priority === 'medium'" class="flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700"><Minus class="h-3 w-3" /> Medium</span>
                <span v-else class="flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700"><ArrowDown class="h-3 w-3" /> Low</span>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="statusStyle[doc.status] || 'bg-gray-100 text-gray-600'">{{ statusLabel[doc.status] || doc.status }}</span>
            </div>
        </div>
    </div>
</template>
