<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Shield, Plus, Trash2, User } from 'lucide-vue-next';

const props = defineProps<{
    departments: { id: number; name: string }[];
    allUsers: { id: number; name: string; email: string }[];
}>();

const showForm = ref(false);
const form = ref({ user_id: '', department_id: '' });

function openAssign() {
    form.value = { user_id: '', department_id: '' };
    showForm.value = true;
}

function assign() {
    router.post('/edts/admins', form.value, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { showForm.value = false; },
    });
}

function confirmRemove(adminId: number) {
    if (confirm('Remove this admin?')) {
        router.delete(`/edts/admins/${adminId}`, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Department Admins</h2>
            <button @click="openAssign" class="flex items-center gap-1.5 rounded-lg bg-[#185FA5] px-3 py-1.5 text-xs font-medium text-white transition hover:bg-[#0C447C]"><Plus class="h-3.5 w-3.5" /> Assign admin</button>
        </div>

        <div v-if="showForm" class="rounded-xl border border-[#B5D4F4] bg-[#f7fbff] p-4">
            <div class="flex flex-col gap-3">
                <select v-model="form.department_id" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-[#185FA5]">
                    <option value="">Select department</option>
                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                </select>
                <select v-model="form.user_id" class="rounded-lg border border-gray-200 px-3 py-2 text-sm outline-none focus:border-[#185FA5]">
                    <option value="">Select user</option>
                    <option v-for="u in allUsers" :key="u.id" :value="u.id">{{ u.name }} ({{ u.email }})</option>
                </select>
                <div class="flex gap-2">
                    <button @click="assign" class="rounded-lg bg-[#185FA5] px-3 py-1.5 text-xs font-medium text-white hover:bg-[#0C447C]">Assign</button>
                    <button @click="showForm = false" class="rounded-lg border px-3 py-1.5 text-xs text-gray-600">Cancel</button>
                </div>
            </div>
        </div>

        <div v-for="dept in departments" :key="dept.id" class="rounded-xl border border-gray-200/60 bg-white dark:border-gray-700/40 dark:bg-gray-800/60">
            <div class="border-b border-gray-100 px-4 py-2.5 text-xs font-semibold text-gray-600 dark:border-gray-700/40 dark:text-gray-300">{{ dept.name }}</div>
            <div v-for="admin in (dept as any).admins || []" :key="admin.id" class="flex items-center gap-3 border-b border-gray-50 px-4 py-2.5 last:border-0 dark:border-gray-700/20">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-[#E6F1FB]">
                    <User class="h-4 w-4 text-[#185FA5]" />
                </div>
                <div class="min-w-0 flex-1">
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ admin.user?.name }}</div>
                    <div class="text-xs text-gray-500">{{ admin.user?.email }}</div>
                </div>
                <button @click="confirmRemove(admin.id)" class="rounded-lg p-1.5 text-gray-400 transition hover:bg-red-50 hover:text-red-500"><Trash2 class="h-4 w-4" /></button>
            </div>
            <div v-if="!dept.admins?.length" class="px-4 py-3 text-xs text-gray-400">No admins assigned.</div>
        </div>
    </div>
</template>
