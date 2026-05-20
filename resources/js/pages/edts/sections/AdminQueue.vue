<script setup lang="ts">
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Search, Eye, RefreshCw, Send, X } from 'lucide-vue-next';

const props = defineProps<{
    adminDocs: any[];
    allUsers: { id: number; name: string; email: string }[];
    departments: { id: number; name: string }[];
}>();

const searchQuery = ref('');
const showModal = ref(false);
const modalStatus = ref('pending');
const modalNotes = ref('');
const activeDoc = ref<any>(null);
const showDetail = ref(false);
const showForwardModal = ref(false);
const forwardDeptId = ref<number | string>('');
const forwardNotes = ref('');

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'review', label: 'Review' },
    { value: 'receive', label: 'Receive' },
    { value: 'in_transit', label: 'In Transit' },
    { value: 'ready', label: 'Ready' },
    { value: 'completed', label: 'Completed' },
    { value: 'rejected', label: 'Rejected' },
];

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

const filteredDocs = computed(() => {
    if (!searchQuery.value) return props.adminDocs;
    const q = searchQuery.value.toLowerCase();
    return props.adminDocs.filter(d =>
        (d.reference_number || '').toLowerCase().includes(q) ||
        (d.requester_name || '').toLowerCase().includes(q) ||
        (d.department_from?.name || '').toLowerCase().includes(q) ||
        (statusLabel[d.status] || d.status || '').toLowerCase().includes(q)
    );
});

function openStatusModal(doc: any) {
    activeDoc.value = doc;
    modalStatus.value = doc.status || 'pending';
    modalNotes.value = '';
    showModal.value = true;
}

function openDetail(doc: any) {
    activeDoc.value = doc;
    showDetail.value = true;
}

function doUpdate() {
    if (!activeDoc.value) return;
    router.put(`/edts/${activeDoc.value.id}`, {
        status: modalStatus.value,
        notes: modalNotes.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showModal.value = false;
            activeDoc.value = null;
        },
    });
}

function openForwardModal(doc: any) {
    activeDoc.value = doc;
    forwardDeptId.value = '';
    forwardNotes.value = '';
    showForwardModal.value = true;
}

function doForward() {
    if (!activeDoc.value || !forwardDeptId.value) return;
    router.post(`/edts/${activeDoc.value.id}/forward`, {
        department_to_id: forwardDeptId.value,
        notes: forwardNotes.value,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            showForwardModal.value = false;
            activeDoc.value = null;
        },
    });
}

function closeModal() {
    showModal.value = false;
    activeDoc.value = null;
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Document Requests</h2>
            <div class="relative">
                <Search class="absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search requests..."
                    class="w-52 rounded-lg border border-gray-200 py-1.5 pl-8 pr-3 text-xs outline-none transition focus:border-[#185FA5]"
                />
            </div>
        </div>

        <div v-if="filteredDocs.length === 0" class="flex flex-col items-center justify-center rounded-xl border border-dashed border-gray-300 bg-white px-6 py-14 text-center dark:border-gray-600 dark:bg-gray-800/60">
            <p class="text-sm text-gray-500">No requests found.</p>
        </div>

        <div v-else class="overflow-hidden rounded-xl border border-gray-200/60 bg-white dark:border-gray-700/40 dark:bg-gray-800/60">
            <div class="overflow-x-auto">
                <table class="w-full" style="table-layout:fixed;">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/80 dark:border-gray-700/40">
                            <th class="px-3 py-2.5 text-left text-[11px] font-medium text-gray-500" style="width:22%;">Tracking number</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-medium text-gray-500" style="width:17%;">Full name</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-medium text-gray-500" style="width:16%;">Department</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-medium text-gray-500" style="width:13%;">Status</th>
                            <th class="px-3 py-2.5 text-left text-[11px] font-medium text-gray-500" style="width:19%;">Created at</th>
                            <th class="px-3 py-2.5 text-center text-[11px] font-medium text-gray-500" style="width:13%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="doc in filteredDocs" :key="doc.id" class="border-b border-gray-50 transition hover:bg-gray-50/50 last:border-0 dark:border-gray-700/20">
                            <td class="px-3 py-2.5 text-[11px] font-mono text-gray-900 truncate">{{ doc.reference_number }}</td>
                            <td class="px-3 py-2.5 text-[12px] text-gray-900 truncate">{{ doc.requester_name }}</td>
                            <td class="px-3 py-2.5 text-[11px] text-gray-600 truncate">
                                <template v-if="doc.department_to">
                                    <span class="text-gray-400">From </span>{{ doc.forwarded_from?.name || doc.department_from?.name }}
                                </template>
                                <template v-else>{{ doc.department_from?.name }}</template>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-block rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusStyle[doc.status] || 'bg-gray-100 text-gray-600'">
                                    {{ statusLabel[doc.status] || doc.status }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-[11px] text-gray-500 truncate">{{ doc.created_at?.slice(0, 10) }}</td>
                            <td class="px-3 py-2.5">
                                <div class="flex items-center justify-center gap-1.5">
                                    <button @click="openDetail(doc)" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-[#185FA5]" title="View detail">
                                        <Eye class="h-4 w-4" />
                                    </button>
                                    <button @click="openForwardModal(doc)" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-[#185FA5]" title="Forward to department">
                                        <Send class="h-4 w-4" />
                                    </button>
                                    <button @click="openStatusModal(doc)" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-[#185FA5]" title="Update status">
                                        <RefreshCw class="h-4 w-4" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Detail Modal -->
        <Teleport to="body">
            <div v-if="showDetail && activeDoc" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showDetail = false">
                <div class="mx-4 w-full max-w-lg rounded-xl bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Request Detail</h3>
                        <button @click="showDetail = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><X class="h-4 w-4" /></button>
                    </div>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div><span class="block text-[11px] text-gray-400">Tracking number</span><span class="font-mono text-[12px] font-medium text-gray-900">{{ activeDoc.reference_number }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Status</span>
                            <span class="mt-0.5 inline-block rounded-full px-2 py-0.5 text-[10px] font-medium" :class="statusStyle[activeDoc.status] || 'bg-gray-100 text-gray-600'">{{ statusLabel[activeDoc.status] || activeDoc.status }}</span>
                        </div>
                        <div><span class="block text-[11px] text-gray-400">Full name</span><span class="font-medium text-gray-900">{{ activeDoc.requester_name }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Department</span><span class="font-medium text-gray-900">{{ activeDoc.department_from?.name }}</span></div>
                        <div v-if="activeDoc.department_to"><span class="block text-[11px] text-gray-400">Transferred from</span><span class="font-medium text-gray-900">{{ activeDoc.forwarded_from?.name || activeDoc.department_from?.name }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Document type</span><span class="font-medium text-gray-900">
                            <template v-if="activeDoc.doc_types?.length">{{ activeDoc.doc_types.map((dt: any) => dt.name).join(', ') }}</template>
                            <template v-else-if="activeDoc.doc_type_other">{{ activeDoc.doc_type_other }}</template>
                            <template v-else>—</template>
                        </span></div>
                        <div><span class="block text-[11px] text-gray-400">Purpose</span><span class="font-medium text-gray-900">{{ activeDoc.purpose }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Email</span><span class="font-medium text-gray-900">{{ activeDoc.requester_email }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Phone</span><span class="font-medium text-gray-900">{{ activeDoc.requester_phone || '—' }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Created at</span><span class="font-medium text-gray-900">{{ activeDoc.created_at?.slice(0, 10) }}</span></div>
                        <div><span class="block text-[11px] text-gray-400">Priority</span>
                            <span class="font-medium" :class="activeDoc.priority === 'high' ? 'text-red-600' : activeDoc.priority === 'low' ? 'text-green-600' : 'text-amber-600'">{{ activeDoc.priority || '—' }}</span>
                        </div>
                    </div>
                    <div v-if="activeDoc.notes" class="mt-4 rounded-lg bg-gray-50 p-3 text-xs text-gray-600">{{ activeDoc.notes }}</div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button @click="openStatusModal(activeDoc); showDetail = false;" class="rounded-lg bg-[#185FA5] px-4 py-2 text-xs font-medium text-white transition hover:bg-[#0C447C]">
                            Update status
                        </button>
                        <button @click="showDetail = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs text-gray-600 transition hover:bg-gray-50">Close</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Status Update Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="closeModal">
                <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Update request status</h3>
                        <button @click="closeModal" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><X class="h-4 w-4" /></button>
                    </div>
                    <label class="mb-1 block text-[12px] text-gray-500">New status</label>
                    <select v-model="modalStatus" class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5]">
                        <option v-for="opt in statusOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <label class="mb-1 block text-[12px] text-gray-500">Notes</label>
                    <textarea v-model="modalNotes" class="mb-5 w-full min-h-[80px] rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] resize-y" placeholder="Add a note (optional)..."></textarea>
                    <div class="flex justify-end gap-2">
                        <button @click="closeModal" class="rounded-lg border border-gray-200 px-4 py-2 text-xs text-gray-600 transition hover:bg-gray-50">Cancel</button>
                        <button @click="doUpdate" class="rounded-lg bg-[#185FA5] px-5 py-2 text-xs font-medium text-white transition hover:bg-[#0C447C]">Update</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Forward Modal -->
        <Teleport to="body">
            <div v-if="showForwardModal && activeDoc" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40" @click.self="showForwardModal = false">
                <div class="mx-4 w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Forward to department</h3>
                        <button @click="showForwardModal = false" class="rounded-lg p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600"><X class="h-4 w-4" /></button>
                    </div>
                    <p class="mb-3 text-xs text-gray-500">Reference: <span class="font-mono font-medium text-gray-700">{{ activeDoc.reference_number }}</span></p>
                    <label class="mb-1 block text-[12px] text-gray-500">Target department</label>
                    <select v-model="forwardDeptId" class="mb-3 w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5]">
                        <option value="" disabled>Select department...</option>
                        <option v-for="dept in departments.filter(d => d.id !== activeDoc.department_from?.id)" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                    </select>
                    <label class="mb-1 block text-[12px] text-gray-500">Notes</label>
                    <textarea v-model="forwardNotes" class="mb-5 w-full min-h-[80px] rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] resize-y" placeholder="Reason for forwarding (optional)..."></textarea>
                    <div class="flex justify-end gap-2">
                        <button @click="showForwardModal = false" class="rounded-lg border border-gray-200 px-4 py-2 text-xs text-gray-600 transition hover:bg-gray-50">Cancel</button>
                        <button @click="doForward" class="rounded-lg bg-[#185FA5] px-5 py-2 text-xs font-medium text-white transition hover:bg-[#0C447C]">Forward</button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>