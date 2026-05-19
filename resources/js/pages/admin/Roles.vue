<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Pencil, Trash2, Plus, X, Check } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface RoleData { id: number; name: string; }

const props = defineProps<{
    roles: RoleData[];
}>();

const showCreateDialog = ref(false);
const editingRoleId = ref<number | null>(null);
const editName = ref('');
const newRoleName = ref('');

function openCreate() {
    newRoleName.value = '';
    showCreateDialog.value = true;
}

function createRole() {
    if (!newRoleName.value.trim()) return;
    router.post('/admin/roles', { name: newRoleName.value }, {
        preserveScroll: true,
        onSuccess: () => { showCreateDialog.value = false; },
    });
}

function startRename(role: RoleData) {
    editingRoleId.value = role.id;
    editName.value = role.name;
}

function saveRename(role: RoleData) {
    if (!editName.value.trim()) return;
    router.patch(`/admin/roles/${role.id}`, { name: editName.value }, {
        preserveScroll: true,
        onSuccess: () => { editingRoleId.value = null; },
    });
}

function cancelRename() {
    editingRoleId.value = null;
}

function deleteRole(role: RoleData) {
    if (!confirm(`Delete role "${role.name}"?`)) return;
    router.delete(`/admin/roles/${role.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head title="Admin Roles" />
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-bold text-[#0F1623]">Roles</h1>
            <Button size="sm" class="bg-[#2563EB] text-white hover:bg-[#1d4ed8]" @click="openCreate">
                <Plus class="mr-1 h-4 w-4" /> Create Role
            </Button>
        </div>

        <div class="rounded-2xl border border-[#E4E7F0] bg-white">
            <div v-if="roles.length === 0" class="px-6 py-12 text-center text-sm text-[#9BA3B8]">
                No roles defined yet.
            </div>
            <div v-else class="flex flex-col">
                <div v-for="(role, idx) in roles" :key="role.id" class="flex items-center justify-between border-b border-[#E4E7F0] px-5 py-3.5 last:border-0">
                    <div class="flex items-center gap-3">
                        <span class="flex h-7 w-7 items-center justify-center rounded-full bg-[#EEF3FF] text-[11px] font-bold text-[#2563EB]">{{ idx + 1 }}</span>
                        <div v-if="editingRoleId === role.id" class="flex items-center gap-2">
                            <input v-model="editName" class="rounded-lg border border-[#2563EB] px-3 py-1.5 text-[13px] outline-none" @keydown.enter="saveRename(role)" @keydown.escape="cancelRename" />
                            <button class="rounded-lg p-1 text-green-600 hover:bg-green-50" @click="saveRename(role)"><Check class="h-4 w-4" /></button>
                            <button class="rounded-lg p-1 text-gray-400 hover:bg-gray-100" @click="cancelRename"><X class="h-4 w-4" /></button>
                        </div>
                        <span v-else class="text-[14px] font-medium text-[#0F1623]">{{ role.name }}</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button class="rounded-lg p-1.5 text-[#5A6278] hover:bg-[#EEF3FF] hover:text-[#2563EB]" title="Rename" @click="startRename(role)">
                            <Pencil class="h-4 w-4" />
                        </button>
                        <button class="rounded-lg p-1.5 text-[#5A6278] hover:bg-red-50 hover:text-red-500" title="Delete" @click="deleteRole(role)">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <Dialog v-model:open="showCreateDialog">
        <DialogContent>
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Create Role</DialogTitle>
                <DialogDescription>Add a new global role.</DialogDescription>
            </DialogHeader>
            <div class="flex flex-col gap-3">
                <div>
                    <Label class="text-xs text-[#5A6278]">Role Name</Label>
                    <Input v-model="newRoleName" placeholder="e.g. Developer" class="border-[#E4E7F0]" @keydown.enter="createRole" />
                </div>
                <div class="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" @click="showCreateDialog = false">Cancel</Button>
                    <Button :disabled="!newRoleName.trim()" size="sm" class="bg-[#2563EB] text-white" @click="createRole">Create</Button>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>
