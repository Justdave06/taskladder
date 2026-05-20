<script setup lang="ts">
import { ref, computed } from 'vue';
import { Search, Clock, Check, Circle } from 'lucide-vue-next';

const refNumber = ref('');
const trackingResult = ref<any>(null);
const loading = ref(false);
const error = ref('');

async function doTrack() {
    if (!refNumber.value.trim()) { error.value = 'Please enter a reference number.'; return; }
    error.value = '';
    loading.value = true;
    try {
        const res = await fetch(`/edts/${encodeURIComponent(refNumber.value.trim())}`);
        if (!res.ok) { error.value = 'Document not found.'; trackingResult.value = null; return; }
        trackingResult.value = await res.json();
    } catch {
        error.value = 'An error occurred.';
    } finally {
        loading.value = false;
    }
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
    in_transit: 'Processing',
    ready: 'Ready',
    completed: 'Completed',
    rejected: 'Rejected',
};

interface TimelineStep {
    dept: string;
    note: string;
    date: string | null;
    state: 'done' | 'active' | 'pending';
}

const timelineSteps = computed<TimelineStep[]>(() => {
    const doc = trackingResult.value;
    if (!doc) return [];

    const steps: TimelineStep[] = [];
    const deptName = doc.department_from?.name || 'Requesting Office';

    // Step 1: Initial submission (always)
    steps.push({
        dept: 'Requesting Office',
        note: 'Document request received and logged.',
        date: doc.created_at,
        state: 'done',
    });

    // Step 2: Review - when admin marks as review
    if (doc.reviewed_at || doc.status === 'review' || doc.status === 'receive' || doc.status === 'in_transit' || doc.status === 'ready' || doc.status === 'completed') {
        steps.push({
            dept: deptName,
            note: 'Your request is being reviewed.',
            date: doc.reviewed_at || doc.updated_at,
            state: doc.status === 'pending' ? 'active' : 'done',
        });
    }

    // Step 3: Receive - admin received the request
    if (doc.received_at && (doc.status === 'receive' || doc.status === 'in_transit' || doc.status === 'ready' || doc.status === 'completed')) {
        steps.push({
            dept: deptName,
            note: doc.notes ? `Received. Note: ${doc.notes}` : 'Your request has been received.',
            date: doc.received_at,
            state: doc.status === 'receive' || doc.status === 'pending' || doc.status === 'review' ? 'active' : 'done',
        });
    }

    // Step 4: Forwarded to another department
    if (doc.department_to) {
        const fromName = doc.forwarded_from?.name || deptName;
        steps.push({
            dept: doc.department_to.name,
            note: doc.notes
                ? `Transferred from ${fromName} to ${doc.department_to.name}. Note: ${doc.notes}`
                : `Transferred from ${fromName} to ${doc.department_to.name}.`,
            date: doc.updated_at,
            state: doc.status === 'completed' || doc.status === 'ready' ? 'done' : (doc.status === 'in_transit' ? 'active' : 'pending'),
        });
    }

    // Step 5: Ready for pickup
    if (doc.ready_at || doc.status === 'ready' || doc.status === 'completed') {
        steps.push({
            dept: deptName,
            note: doc.ready_at
                ? 'The document is ready! Get the document on ' + new Date(doc.ready_at).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) + ' at the office.'
                : 'The document is ready!',
            date: doc.ready_at || doc.updated_at,
            state: doc.status === 'completed' ? 'done' : doc.status === 'ready' ? 'active' : 'done',
        });
    }

    // Step 6: Completed
    if (doc.completed_at) {
        steps.push({
            dept: deptName,
            note: 'Document request completed.',
            date: doc.completed_at,
            state: 'done',
        });
    }

    // If rejected, add a rejection step
    if (doc.status === 'rejected') {
        steps.push({
            dept: deptName,
            note: doc.notes ? `Request rejected. Reason: ${doc.notes}` : 'Request rejected.',
            date: doc.updated_at,
            state: 'done',
        });
    }

    return steps;
});

function formatDate(dateStr: string | null) {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="mx-auto max-w-lg">
        <div class="rounded-xl border border-gray-200/60 bg-white p-6 shadow-sm dark:border-gray-700/40 dark:bg-gray-800/60">
            <h2 class="mb-5 text-center text-lg font-semibold text-gray-900 dark:text-gray-100">Track your request</h2>
            <div class="mb-3">
                <label class="mb-1.5 block text-xs font-medium text-gray-500">Enter tracking number</label>
                <input v-model="refNumber" @keyup.enter="doTrack" class="w-full rounded-lg border border-gray-200 px-3.5 py-2.5 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="e.g. EDTS-20260520-XXXXXXXX" />
            </div>
            <button @click="doTrack" :disabled="loading" class="flex w-full items-center justify-center gap-2 rounded-lg bg-[#185FA5] px-4 py-2.5 text-sm font-medium text-white transition hover:bg-[#0C447C]">
                <Search class="h-4 w-4" /> {{ loading ? 'Searching...' : 'Track document' }}
            </button>
            <span v-if="error" class="mt-2 block text-xs text-red-500">{{ error }}</span>

            <div v-if="trackingResult" class="mt-6 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-800/60">
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Request status</span>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium" :class="statusStyle[trackingResult.status] || 'bg-gray-100 text-gray-600'">{{ statusLabel[trackingResult.status] || trackingResult.status }}</span>
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                        <div><span class="block text-[11px] text-gray-400">Request ID</span><span class="font-mono text-xs font-medium">{{ trackingResult.reference_number }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Request date</span><span class="text-xs">{{ trackingResult.created_at?.slice(0, 10) }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Requester</span><span class="text-xs font-medium">{{ trackingResult.requester_name }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Department</span><span class="text-xs font-medium">{{ trackingResult.department_from?.name }}</span></div>
                    </div>
                </div>

                <div>
                    <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Status timeline</p>
                    <div class="relative pl-8">
                        <div class="absolute left-[11px] top-0 h-full w-0.5 bg-gray-200 dark:bg-gray-600" />

                        <div v-for="(step, si) in timelineSteps" :key="si" class="relative mb-3 z-10">
                            <div class="absolute -left-[21px] top-3.5 flex h-[18px] w-[18px] items-center justify-center rounded-full border-2 text-[9px] transition"
                                :class="step.state === 'done' ? 'border-[#185FA5] bg-[#185FA5] text-white' : step.state === 'active' ? 'border-[#185FA5] bg-white text-[#185FA5] shadow-[0_0_0_3px_#E6F1FB]' : 'border-gray-300 bg-gray-100 text-gray-400 dark:border-gray-500 dark:bg-gray-700'"
                            >
                                <Check v-if="step.state === 'done'" class="h-2.5 w-2.5" />
                                <Circle v-else-if="step.state === 'pending'" class="h-2.5 w-2.5" />
                                <span v-else class="h-1.5 w-1.5 rounded-full bg-[#185FA5]" />
                            </div>
                            <div class="rounded-lg border p-2.5 text-xs"
                                :class="step.state === 'done' ? 'border-[#B5D4F4] bg-[#f7fbff]' : step.state === 'active' ? 'border-[#378ADD] bg-[#E6F1FB]' : 'border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700/40'"
                            >
                                <div class="font-medium text-gray-900 dark:text-gray-100" :class="step.state !== 'pending' ? 'text-[#0C447C]' : ''">{{ step.dept }}</div>
                                <div class="mt-0.5 text-gray-500">{{ step.note }}</div>
                                <div v-if="step.date" class="mt-1 flex items-center gap-1 text-[10px] text-gray-400">
                                    <Clock class="h-3 w-3" /> {{ formatDate(step.date) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
