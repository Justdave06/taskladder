<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Pencil, Trash2, Plus, UserCog, Upload } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface CompanyData {
    id: number; name: string; logo: string | null; logo_url: string | null; color: string | null;
    created_by: number; creator: { id: number; name: string };
    users: { id: number; name: string; email: string; is_company_admin: boolean }[];
}
interface UserData { id: number; name: string; email: string; }

const props = defineProps<{
    companies: CompanyData[];
    users: UserData[];
}>();

const showCreateDialog = ref(false);
const showEditDialog = ref(false);
const showAdminDialog = ref(false);
const editingCompany = ref<CompanyData | null>(null);
const selectedCompany = ref<CompanyData | null>(null);
const form = ref({ name: '', logo: null as File | null });
const logoPreview = ref<string | null>(null);
const adminForm = ref({ user_id: '' });

function onLogoSelected(event: Event) {
    const input = event.target as HTMLInputElement;
    if (input.files && input.files[0]) {
        form.value.logo = input.files[0];
        const reader = new FileReader();
        reader.onload = (e) => { logoPreview.value = e.target?.result as string; };
        reader.readAsDataURL(input.files[0]);
    }
}

function logoUrl(company: CompanyData): string | null {
    return company.logo_url || null;
}

function textColor(hex: string | null): string {
    if (!hex) return '#0F1623';
    const r = parseInt(hex.slice(1, 3), 16);
    const g = parseInt(hex.slice(3, 5), 16);
    const b = parseInt(hex.slice(5, 7), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.55 ? '#0F1623' : '#FFFFFF';
}

const palette = computed(() => {
    const colors = ['#3B82F6','#8B5CF6','#EC4899','#EF4444','#F97316','#EAB308','#22C55E','#14B8A6','#06B6D4','#6366F1','#D946EF','#84CC16'];
    return props.companies.map((c, i) => c.color || colors[i % colors.length]);
});

function adminName(company: CompanyData): string {
    const admin = company.users.find(u => u.is_company_admin);
    return admin?.name || '—';
}

function openCreate() {
    form.value = { name: '', logo: null };
    logoPreview.value = null;
    showCreateDialog.value = true;
}

function openEdit(company: CompanyData) {
    editingCompany.value = company;
    form.value = { name: company.name };
    showEditDialog.value = true;
}

function openAssignAdmin(company: CompanyData) {
    selectedCompany.value = company;
    adminForm.value = { user_id: '' };
    showAdminDialog.value = true;
}

function createCompany() {
    if (!form.value.name.trim()) return;
    const payload = new FormData();
    payload.append('name', form.value.name);
    if (form.value.logo) payload.append('logo', form.value.logo);
    router.post('/admin/companies', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
        preserveScroll: true,
        onSuccess: () => { showCreateDialog.value = false; },
        onError: (errors) => { alert(Object.values(errors).join('\n')); },
    });
}

function updateCompany() {
    if (!editingCompany.value || !form.value.name.trim()) return;
    router.patch(`/admin/companies/${editingCompany.value.id}`, form.value, {
        preserveScroll: true,
        onSuccess: () => { showEditDialog.value = false; editingCompany.value = null; },
    });
}

function deleteCompany(company: CompanyData) {
    if (!confirm(`Delete company "${company.name}"?`)) return;
    router.delete(`/admin/companies/${company.id}`, {
        preserveScroll: true,
        onError: (errors) => { alert(Object.values(errors).join('\n')); },
    });
}

function assignAdmin() {
    if (!selectedCompany.value || !adminForm.value.user_id) return;
    router.post(`/admin/companies/${selectedCompany.value.id}/assign-admin`, adminForm.value, {
        preserveScroll: true,
        onSuccess: () => { showAdminDialog.value = false; selectedCompany.value = null; },
        onError: (errors) => { alert(Object.values(errors).join('\n')); },
    });
}
</script>

<template>
    <Head title="Admin Companies" />
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-[#0F1623]">Companies</h1>
            <Button size="sm" class="bg-[#2563EB] text-white hover:bg-[#1d4ed8]" @click="openCreate">
                <Plus class="mr-1 h-4 w-4" /> Create Company
            </Button>
        </div>

        <div class="overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white">
            <div v-if="companies.length === 0" class="px-6 py-12 text-center text-sm text-[#9BA3B8]">
                No companies yet.
            </div>
            <table v-else class="w-full text-left text-[13px]">
                <thead class="border-b border-[#E4E7F0] bg-[#F7F8FC]">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-[#5A6278]">Company Name</th>
                        <th class="px-4 py-3 font-semibold text-[#5A6278]">Admin</th>
                        <th class="px-4 py-3 font-semibold text-[#5A6278]">Created By</th>
                        <th class="px-4 py-3 font-semibold text-[#5A6278]">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(company, i) in companies" :key="company.id" class="border-b border-[#E4E7F0] last:border-0 hover:bg-[#F7F8FC]">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg text-[11px] font-bold"
                                    :style="{ backgroundColor: company.color || palette[i], color: textColor(company.color || palette[i]) }"
                                >
                                    <img v-if="company.logo_url" :src="company.logo_url" :alt="company.name" class="h-full w-full object-cover" />
                                    <span v-else>{{ company.name.charAt(0).toUpperCase() }}</span>
                                </div>
                                <span class="font-medium text-[#0F1623]">{{ company.name }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-[#5A6278]">{{ adminName(company) }}</td>
                        <td class="px-4 py-3 text-[#5A6278]">{{ company.creator?.name || '—' }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1">
                                <button class="rounded-lg p-1.5 text-[#5A6278] transition-colors hover:bg-[#EEF3FF] hover:text-[#2563EB]" title="Assign Admin" @click="openAssignAdmin(company)">
                                    <UserCog class="h-4 w-4" />
                                </button>
                                <button class="rounded-lg p-1.5 text-[#5A6278] transition-colors hover:bg-[#EEF3FF] hover:text-[#2563EB]" title="Edit" @click="openEdit(company)">
                                    <Pencil class="h-4 w-4" />
                                </button>
                                <button class="rounded-lg p-1.5 text-[#5A6278] transition-colors hover:bg-red-50 hover:text-red-500" title="Delete" @click="deleteCompany(company)">
                                    <Trash2 class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <Dialog v-model:open="showCreateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Create Company</DialogTitle>
                <DialogDescription>Add a new company.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div>
                    <Label class="text-xs text-[#5A6278]">Company Name</Label>
                    <Input v-model="form.name" placeholder="e.g. Acme Corp" class="border-[#E4E7F0]" @keydown.enter="createCompany" />
                </div>
                <div>
                    <Label class="text-xs text-[#5A6278]">Logo (optional)</Label>
                    <label
                        class="mt-1 flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-[#E4E7F0] px-3 py-2.5 text-sm text-[#5A6278] transition-colors hover:border-[#2563EB] hover:text-[#2563EB]"
                    >
                        <Upload class="h-4 w-4" />
                        {{ form.logo ? form.logo.name : 'Upload logo' }}
                        <input type="file" accept="image/*" class="hidden" @change="onLogoSelected" />
                    </label>
                    <img v-if="logoPreview" :src="logoPreview" class="mt-2 h-14 w-14 rounded-lg object-cover" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showCreateDialog = false">Cancel</Button>
                    <Button :disabled="!form.name.trim()" size="sm" class="bg-[#2563EB] text-white" @click="createCompany">Create</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showEditDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Edit Company</DialogTitle>
                <DialogDescription>Rename the company.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div>
                    <Label class="text-xs text-[#5A6278]">Company Name</Label>
                    <Input v-model="form.name" placeholder="Company name" class="border-[#E4E7F0]" @keydown.enter="updateCompany" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showEditDialog = false">Cancel</Button>
                    <Button :disabled="!form.name.trim()" size="sm" class="bg-[#2563EB] text-white" @click="updateCompany">Save</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="showAdminDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Assign Admin</DialogTitle>
                <DialogDescription>Select a user as the admin for {{ selectedCompany?.name }}.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div>
                    <Label class="text-xs text-[#5A6278]">User</Label>
                    <select v-model="adminForm.user_id" class="flex h-10 w-full rounded-lg border border-[#E4E7F0] bg-white px-3 py-2 text-sm outline-none focus:border-[#2563EB]">
                        <option value="" disabled>Select user</option>
                        <option v-for="u in users" :key="u.id" :value="String(u.id)">{{ u.name }} ({{ u.email }})</option>
                    </select>
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showAdminDialog = false">Cancel</Button>
                    <Button :disabled="!adminForm.user_id" size="sm" class="bg-[#2563EB] text-white" @click="assignAdmin">Assign</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
