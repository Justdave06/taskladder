<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Building2, Plus, Pencil, Trash2, X, Check } from 'lucide-vue-next';

const props = defineProps<{
    departments: { id: number; name: string; description?: string }[];
}>();

const showForm = ref(false);
const editingId = ref<number | null>(null);
const form = ref({ name: '', description: '' });

function openCreate() {
    editingId.value = null;
    form.value = { name: '', description: '' };
    showForm.value = true;
}

function openEdit(dept: { id: number; name: string; description?: string }) {
    editingId.value = dept.id;
    form.value = { name: dept.name, description: dept.description || '' };
    showForm.value = true;
}

function save() {
    if (editingId.value) {
        router.put(`/edts/departments/${editingId.value}`, form.value, { preserveScroll: true, preserveState: true, onSuccess: () => { showForm.value = false; } });
    } else {
        router.post('/edts/departments', form.value, { preserveScroll: true, preserveState: true, onSuccess: () => { showForm.value = false; } });
    }
}

function confirmDelete(id: number) {
    if (confirm('Delete this department?')) {
        router.delete(`/edts/departments/${id}`, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Departments</h2>
            <button @click="openCreate" class="flex items-center gap-1.5 rounded-lg bg-[#185FA5] px-3 py-1.5 text-xs font-medium text-white transition hover:bg-[#0C447C]"><Plus class="h-3.5 w-3.5" /> Add</button>
        </div>

        <div v-if="showForm" class="rounded-xl border border-[#B5D4F4] bg-[#f7fbff] p-4">
            <div class="flex flex-col gap-3">
                <input v-model="form.name" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-[#185FA5]" placeholder="Department name" />
                <input v-model="form.description" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-[#185FA5]" placeholder="Description (optional)" />
                <div class="flex gap-2">
                    <button @click="save" class="flex items-center gap-1 rounded-lg bg-[#185FA5] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#0C447C]"><Check class="h-3.5 w-3.5" /> {{ editingId ? 'Update' : 'Create' }}</button>
                    <button @click="showForm = false" class="flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs text-gray-600"><X class="h-3.5 w-3.5" /> Cancel</button>
                </div>
            </div>
        </div>

        <div v-for="dept in departments" :key="dept.id" class="flex items-center gap-3 rounded-xl border border-gray-200/60 bg-white px-4 py-3 dark:border-gray-700/40 dark:bg-gray-800/60">
            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[#E6F1FB]">
                <Building2 class="h-4 w-4 text-[#185FA5]" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ dept.name }}</div>
                <div v-if="dept.description" class="text-xs text-gray-500">{{ dept.description }}</div>
            </div>
            <button @click="openEdit(dept)" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600"><Pencil class="h-4 w-4" /></button>
            <button @click="confirmDelete(dept.id)" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-500"><Trash2 class="h-4 w-4" /></button>
        </div>
    </div>
</template>
