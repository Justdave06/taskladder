<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { ArrowRight, ArrowLeft, Send, Check, AlertTriangle, Minus, ArrowDown } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    departments: { id: number; name: string; description?: string }[];
    docTypes: { id: number; name: string; description?: string }[];
    isAdmin: boolean;
}>();

const STORAGE_KEY = 'edts_request_draft';

function loadDraft() {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (raw) return JSON.parse(raw);
    } catch {}
    return null;
}

function saveDraft() {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify(form.value));
    } catch {}
}

function clearDraft() {
    try {
        sessionStorage.removeItem(STORAGE_KEY);
    } catch {}
}

const OTHERS_VALUE = '__others__';

const draft = loadDraft();

const step = ref(1);
const submitting = ref(false);

const defaultForm = {
    requester_name: '',
    requester_email: '',
    requester_phone: '',
    department_from_id: '',
    doc_type_id: '',
    doc_type_other: '',
    title: '',
    purpose: '',
    purpose_other: '',
    priority: 'medium',
    notes: '',
};

const form = ref(draft ? { ...defaultForm, ...draft } : { ...defaultForm });

const isOtherDocType = computed(() => form.value.doc_type_id === OTHERS_VALUE);

const errors = ref<Record<string, string>>({});

function goStep(n: number) {
    if (n === 2 && !validateStep1()) return;
    if (n === 3 && !validateStep2()) return;
    step.value = n;
}

function validateStep1() {
    errors.value = {};
    if (!form.value.requester_name.trim()) errors.value.requester_name = 'Required';
    if (!form.value.requester_email.trim()) errors.value.requester_email = 'Required';
    if (!form.value.department_from_id) errors.value.department_from_id = 'Required';
    return Object.keys(errors.value).length === 0;
}

function validateStep2() {
    errors.value = {};
    if (!form.value.doc_type_id) errors.value.doc_type_id = 'Select a document type';
    if (isOtherDocType.value && !form.value.doc_type_other.trim()) errors.value.doc_type_other = 'Please specify the document type';
    if (!form.value.title.trim()) errors.value.title = 'Required';
    if (!form.value.purpose.trim()) errors.value.purpose = 'Required';
    if (form.value.purpose === 'Others' && !form.value.purpose_other.trim()) errors.value.purpose_other = 'Please specify the purpose';
    return Object.keys(errors.value).length === 0;
}

function onDocTypeChange() {
    if (isOtherDocType.value) {
        form.value.doc_type_other = '';
    }
}

function submit() {
    if (!validateStep1() || !validateStep2()) return;
    submitting.value = true;
    const payload: Record<string, any> = { ...form.value };
    if (isOtherDocType.value) {
        payload.doc_type_ids = [];
    } else {
        payload.doc_type_ids = [Number(payload.doc_type_id)];
    }
    delete payload.doc_type_id;
    if (payload.purpose === 'Others' && payload.purpose_other?.trim()) {
        payload.purpose = 'Others: ' + payload.purpose_other.trim();
    }
    delete payload.purpose_other;
    router.post('/edts', payload, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            clearDraft();
            step.value = 1;
            form.value = { ...defaultForm };
            submitting.value = false;
        },
        onError: () => { submitting.value = false; },
    });
}

const selectedDocTypeLabel = computed(() => {
    if (isOtherDocType.value && form.value.doc_type_other) return 'Others: ' + form.value.doc_type_other;
    if (isOtherDocType.value) return 'Others';
    const dt = props.docTypes.find(d => d.id === Number(form.value.doc_type_id));
    return dt?.name || '';
});

watch(form, saveDraft, { deep: true });

const selectedDeptName = computed(() => props.departments.find(d => d.id === Number(form.value.department_from_id))?.name || '');

const priorityIcon = computed(() => {
    if (form.value.priority === 'high') return AlertTriangle;
    if (form.value.priority === 'low') return ArrowDown;
    return Minus;
});
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <div class="rounded-xl border border-gray-200/60 bg-white shadow-sm dark:border-gray-700/40 dark:bg-gray-800/60">
            <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700/40">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Document Request Form</h2>

                <div class="mt-4 flex items-center gap-0">
                    <div v-for="s in 3" :key="s" class="flex items-center">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-medium transition"
                            :class="s < step ? 'bg-[#185FA5] text-white' : s === step ? 'bg-[#185FA5] text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-700'"
                        >
                            <Check v-if="s < step" class="h-3.5 w-3.5" />
                            <span v-else>{{ s }}</span>
                        </div>
                        <span class="ml-1.5 text-xs"
                            :class="s === step ? 'font-medium text-[#185FA5]' : 'text-gray-400'"
                        >{{ ['Personal Info', 'Document Details', 'Review & Submit'][s - 1] }}</span>
                        <div v-if="s < 3" class="mx-3 h-px w-8 bg-gray-200 dark:bg-gray-700" />
                    </div>
                </div>
            </div>

            <div class="px-6 py-5">
                <div v-if="step === 1" class="flex flex-col gap-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-gray-600">Full name</label>
                            <input v-model="form.requester_name" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="e.g. Juan Dela Cruz" />
                            <span v-if="errors.requester_name" class="text-[11px] text-red-500">{{ errors.requester_name }}</span>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-gray-600">Requesting for</label>
                            <select v-model="form.department_from_id" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10">
                                <option value="">Select department</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                            <span v-if="errors.department_from_id" class="text-[11px] text-red-500">{{ errors.department_from_id }}</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-gray-600">Email address</label>
                            <input v-model="form.requester_email" type="email" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="you@email.com" />
                            <span v-if="errors.requester_email" class="text-[11px] text-red-500">{{ errors.requester_email }}</span>
                        </div>
                        <div class="flex flex-col gap-1.5">
                            <label class="text-xs font-medium text-gray-600">Contact phone</label>
                            <input v-model="form.requester_phone" type="tel" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="09XX XXX XXXX" />
                        </div>
                    </div>
                    <div class="mt-4 flex justify-end">
                        <Button @click="goStep(2)" class="bg-[#185FA5] hover:bg-[#0C447C]">
                            Continue <ArrowRight class="ml-1.5 h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div v-if="step === 2" class="flex flex-col gap-4">
                    <div>
                        <label class="mb-1.5 block text-xs font-medium text-gray-600">Document type</label>
                        <select v-model="form.doc_type_id" @change="onDocTypeChange" class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10">
                            <option value="">Select document type</option>
                            <option v-for="dt in docTypes" :key="dt.id" :value="dt.id">{{ dt.name }}</option>
                            <option :value="OTHERS_VALUE">Others</option>
                        </select>
                        <span v-if="errors.doc_type_id" class="mt-1 block text-[11px] text-red-500">{{ errors.doc_type_id }}</span>
                        <input v-if="isOtherDocType" v-model="form.doc_type_other" class="mt-2 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="Please specify document type..." />
                        <span v-if="errors.doc_type_other" class="text-[11px] text-red-500">{{ errors.doc_type_other }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-gray-600">Document title</label>
                        <input v-model="form.title" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="e.g. Official Transcript Request" />
                        <span v-if="errors.title" class="text-[11px] text-red-500">{{ errors.title }}</span>
                    </div>
                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-gray-600">Purpose</label>
                        <select v-model="form.purpose" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10">
                            <option value="">Select purpose</option>
                            <option>Employment</option>
                            <option>Further studies</option>
                            <option>Board examination</option>
                            <option>Scholarship application</option>
                            <option>Government requirement</option>
                            <option>Others</option>
                        </select>
                        <span v-if="errors.purpose" class="text-[11px] text-red-500">{{ errors.purpose }}</span>
                        <input v-if="form.purpose === 'Others'" v-model="form.purpose_other" class="mt-1 rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="Please specify purpose..." />
                        <span v-if="errors.purpose_other" class="text-[11px] text-red-500">{{ errors.purpose_other }}</span>
                    </div>

                    <div v-if="isAdmin" class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-gray-600">Priority</label>
                        <div class="flex gap-2">
                            <button
                                v-for="p in [{ key: 'low', label: 'Low', icon: ArrowDown, cls: 'border-green-300 bg-green-50 text-green-700 hover:bg-green-100' }, { key: 'medium', label: 'Medium', icon: Minus, cls: 'border-amber-300 bg-amber-50 text-amber-700 hover:bg-amber-100' }, { key: 'high', label: 'High', icon: AlertTriangle, cls: 'border-red-300 bg-red-50 text-red-700 hover:bg-red-100' }]"
                                :key="p.key"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-lg border-2 px-3 py-2 text-xs font-medium transition"
                                :class="form.priority === p.key ? p.cls + ' ring-2 ring-offset-1' : 'border-gray-200 bg-white text-gray-500 hover:bg-gray-50'"
                                @click="form.priority = p.key"
                            >
                                <component :is="p.icon" class="h-4 w-4" />
                                {{ p.label }}
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1.5">
                        <label class="text-xs font-medium text-gray-600">Additional notes <span class="font-normal text-gray-400">(optional)</span></label>
                        <textarea v-model="form.notes" rows="3" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none transition focus:border-[#185FA5] focus:ring-2 focus:ring-[#185FA5]/10" placeholder="Any special instructions..." />
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#185FA5]" @click="goStep(1)"><ArrowLeft class="h-4 w-4" /> Back</button>
                        <Button @click="goStep(3)" class="bg-[#185FA5] hover:bg-[#0C447C]">
                            Continue <ArrowRight class="ml-1.5 h-4 w-4" />
                        </Button>
                    </div>
                </div>

                <div v-if="step === 3" class="flex flex-col gap-4">
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Personal Information</p>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div><span class="block text-xs text-gray-400">Full name</span><span class="font-medium">{{ form.requester_name }}</span></div>
                            <div><span class="block text-xs text-gray-400">Department</span><span class="font-medium">{{ selectedDeptName }}</span></div>
                            <div><span class="block text-xs text-gray-400">Email</span><span class="font-medium">{{ form.requester_email }}</span></div>
                            <div><span class="block text-xs text-gray-400">Phone</span><span class="font-medium">{{ form.requester_phone || '—' }}</span></div>
                        </div>
                    </div>
                    <div class="h-px bg-gray-200 dark:bg-gray-700" />
                    <div>
                        <p class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-500">Document Details</p>
                        <div class="flex flex-col gap-2 text-sm">
                            <div><span class="block text-xs text-gray-400">Document type</span>
                                <span class="mt-1 inline-block rounded-full bg-[#E6F1FB] px-2.5 py-0.5 text-xs text-[#0C447C]">{{ selectedDocTypeLabel || '—' }}</span>
                            </div>
                            <div><span class="block text-xs text-gray-400">Title</span><span class="font-medium">{{ form.title }}</span></div>
                            <div><span class="block text-xs text-gray-400">Purpose</span><span class="font-medium">{{ form.purpose === 'Others' && form.purpose_other ? 'Others: ' + form.purpose_other : form.purpose }}</span></div>
                            <div v-if="isAdmin"><span class="block text-xs text-gray-400">Priority</span>
                                <span class="mt-0.5 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="form.priority === 'high' ? 'bg-red-100 text-red-700' : form.priority === 'low' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'"
                                >
                                    <component :is="priorityIcon" class="h-3 w-3" />
                                    {{ form.priority?.charAt(0).toUpperCase() + form.priority?.slice(1) }}
                                </span>
                            </div>
                            <div v-if="form.notes"><span class="block text-xs text-gray-400">Notes</span><span>{{ form.notes }}</span></div>
                        </div>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <button class="flex items-center gap-1.5 text-sm text-gray-500 hover:text-[#185FA5]" @click="goStep(2)"><ArrowLeft class="h-4 w-4" /> Back</button>
                        <Button :disabled="submitting" @click="submit" class="bg-[#185FA5] hover:bg-[#0C447C]">
                            <Send class="mr-1.5 h-4 w-4" /> {{ submitting ? 'Submitting...' : 'Submit request' }}
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
