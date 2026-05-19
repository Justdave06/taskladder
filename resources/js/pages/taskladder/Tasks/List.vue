<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { FileText, File, FileSpreadsheet, Eye, Upload, Pin, PinOff, X, Search, Calendar, ChevronDown, Check } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface ChecklistItem {
    id: number;
    title: string;
    is_completed: boolean;
}

interface TaskData {
    id: number;
    title: string;
    description: string | null;
    priority: string;
    file_type: string | null;
    file_name: string | null;
    file_path: string | null;
    file_url: string | null;
    project_member_id: number | null;
    project: { id: number; title: string };
    project_member?: { id: number; user_id: number; user: { id: number; name: string; email: string } };
    creator: { id: number; name: string } | null;
    checklist_items: ChecklistItem[];
    status: string;
    pinned: boolean;
    updated_at: string;
    created_at: string;
}

const props = defineProps<{
    tasks: TaskData[];
    projects: { id: number; title: string }[];
    completedTasks: TaskData[];
}>();

const activeTab = ref<'all' | 'completed'>('all');
const searchQuery = ref('');

const filteredCompleted = computed(() => {
    if (!searchQuery.value) return props.completedTasks;
    const q = searchQuery.value.toLowerCase();
    return props.completedTasks.filter(t =>
        t.title.toLowerCase().includes(q) ||
        (t.creator?.name || '').toLowerCase().includes(q) ||
        t.project?.title.toLowerCase().includes(q)
    );
});

function fileUrl(task: TaskData): string | null {
    return task.file_url || (task.file_path ? '/storage/' + task.file_path : null);
}

function isImageFile(task: TaskData): boolean {
    if (task.file_type === 'IMAGE') return true;
    const url = fileUrl(task);
    if (!url) return false;
    return /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(url);
}
</script>

<template>
    <Head title="Tasks" />

    <div class="p-6" style="font-family:'DM Sans',sans-serif;">
        <!-- Tab bar -->
        <div class="mb-6 flex items-center gap-3 border-b border-[#E4E7F0] pb-3">
            <button class="rounded-full px-4 py-1.5 text-[12px] font-medium transition-all" :class="activeTab === 'all' ? 'bg-[#0F1623] text-white' : 'text-[#9BA3B8] hover:text-[#5A6278]'" @click="activeTab = 'all'">All Tasks ({{ tasks.length }})</button>
            <button class="rounded-full px-4 py-1.5 text-[12px] font-medium transition-all" :class="activeTab === 'completed' ? 'bg-green-700 text-white' : 'text-[#9BA3B8] hover:text-[#5A6278]'" @click="activeTab = 'completed'">Completed ({{ completedTasks.length }})</button>
        </div>

        <!-- All Tasks grid -->
        <template v-if="activeTab === 'all'">
            <div v-if="tasks.length === 0" class="py-12 text-center text-sm" style="color:#9BA3B8;">
                No tasks yet.
            </div>

            <div v-else class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="task in tasks" :key="task.id" style="background:#fff;border-radius:16px;padding:16px;box-shadow:0 1px 6px rgba(0,0,0,.06);border:1px solid #F0F2F8;">
                    <div class="mb-2 flex items-start justify-between gap-2">
                        <span style="font-size:14px;font-weight:600;color:#0F1623;">{{ task.title }}</span>
                        <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="task.priority === 'high' ? 'bg-red-500' : task.priority === 'med' ? 'bg-amber-500' : task.priority === 'low' ? 'bg-green-500' : 'bg-gray-400'" />
                    </div>
                    <div style="display:flex;align-items:center;gap:8px;font-size:11px;color:#9BA3B8;">
                        <span style="background:#EEF3FF;color:#2563EB;border-radius:4px;padding:2px 6px;font-weight:500;">{{ task.project.title }}</span>
                        <span>{{ task.project_member?.user?.name || 'Unassigned' }}</span>
                    </div>
                    <div v-if="task.checklist_items?.length" class="mt-2">
                        <div style="display:flex;gap:6px;font-size:11px;color:#5A6278;">
                            <span>{{ task.checklist_items.filter(i => i.is_completed).length }}/{{ task.checklist_items.length }}</span>
                        </div>
                        <div class="mt-1" style="height:3px;background:#F0F2F8;border-radius:99px;overflow:hidden;">
                            <div style="height:100%;border-radius:99px;background:#2563EB;transition:width .3s;" :style="{ width: (task.checklist_items.filter(i => i.is_completed).length / task.checklist_items.length * 100) + '%' }"></div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Completed table -->
        <template v-if="activeTab === 'completed'">
            <!-- Search bar -->
            <div class="mb-4 flex items-center gap-2">
                <div style="display:flex;align-items:center;gap:6px;background:#fff;border:1px solid #E4E7F0;border-radius:10px;padding:8px 12px;flex:1;max-width:320px;">
                    <Search class="h-4 w-4" style="color:#9BA3B8;flex-shrink:0;" />
                    <input v-model="searchQuery" type="text" placeholder="Search completed tasks…" style="border:none;outline:none;font-size:13px;flex:1;color:#0F1623;background:transparent;font-family:'DM Sans',sans-serif;" />
                </div>
            </div>

            <div v-if="filteredCompleted.length === 0" class="py-12 text-center text-sm" style="color:#9BA3B8;">
                {{ props.completedTasks.length === 0 ? 'No completed tasks yet.' : 'No tasks match your search.' }}
            </div>

            <div v-else style="background:#fff;border-radius:16px;box-shadow:0 1px 8px rgba(0,0,0,.06);overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr style="background:#F7F8FC;">
                            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#5A6278;text-transform:uppercase;letter-spacing:0.5px;">Task Name</th>
                            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#5A6278;text-transform:uppercase;letter-spacing:0.5px;">Assigner</th>
                            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#5A6278;text-transform:uppercase;letter-spacing:0.5px;">Project</th>
                            <th style="padding:12px 16px;text-align:left;font-size:11px;font-weight:600;color:#5A6278;text-transform:uppercase;letter-spacing:0.5px;">Date &amp; Time</th>
                            <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#5A6278;text-transform:uppercase;letter-spacing:0.5px;">Checklist</th>
                            <th style="padding:12px 16px;text-align:center;font-size:11px;font-weight:600;color:#5A6278;text-transform:uppercase;letter-spacing:0.5px;">File</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in filteredCompleted" :key="task.id" style="border-top:1px solid #F0F2F8;">
                            <td style="padding:12px 16px;font-size:13px;font-weight:500;color:#0F1623;">{{ task.title }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#5A6278;">{{ task.creator?.name || 'Admin' }}</td>
                            <td style="padding:12px 16px;font-size:13px;color:#5A6278;">
                                <span style="background:#EEF3FF;color:#2563EB;border-radius:4px;padding:2px 6px;font-size:11px;font-weight:500;">{{ task.project?.title || '—' }}</span>
                            </td>
                            <td style="padding:12px 16px;font-size:13px;color:#5A6278;">{{ new Date(task.updated_at).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</td>
                            <td style="padding:12px 16px;text-align:center;font-size:12px;color:#2563EB;">{{ task.checklist_items.filter(i => i.is_completed).length }}/{{ task.checklist_items.length }}</td>
                            <td style="padding:12px 16px;text-align:center;">
                                <a v-if="fileUrl(task)" :href="fileUrl(task)!" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:4px;color:#2563EB;font-size:12px;text-decoration:none;">
                                    <Eye class="h-3.5 w-3.5" />
                                    {{ task.file_name || 'View' }}
                                </a>
                                <span v-else style="color:#9BA3B8;font-size:12px;">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </div>
</template>
