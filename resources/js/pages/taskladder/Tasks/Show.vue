<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, FileText, File, FileSpreadsheet, CheckCircle2, Calendar } from 'lucide-vue-next';
import { ref, computed } from 'vue';

interface ChecklistItem {
    id: number;
    title: string;
    is_completed: boolean;
}

interface TaskItem {
    id: number;
    title: string;
    description: string | null;
    completion_notes: string | null;
    deadline: string | null;
    priority: string;
    file_type: string | null;
    file_path: string | null;
    status: string;
    creator: { id: number; name: string };
    project: { id: number; title: string; color: string | null };
    project_member: { id: number; role: string | null; user: { id: number; name: string } } | null;
    checklist_items: ChecklistItem[];
}

const props = defineProps<{ task: TaskItem }>();

const showNotes = ref(false);
const notes = ref(props.task.completion_notes || '');
const submitting = ref(false);

const allDone = computed(() =>
    props.task.checklist_items.length > 0
    && props.task.checklist_items.every((i) => i.is_completed),
);

const fileName = computed(() =>
    props.task.file_path?.split('/').pop() || null,
);

function fileIcon(type: string | null) {
    if (type === 'DOC') {
return FileText;
}

    if (type === 'PDF') {
return File;
}

    if (type === 'XLS') {
return FileSpreadsheet;
}

    return null;
}

function toggleChecklist(item: ChecklistItem) {
    if (props.task.status !== 'accepted') {
return;
}

    router.patch(`/checklist-items/${item.id}`, {
        is_completed: !item.is_completed,
    }, { preserveScroll: true });
}

function markDone() {
    showNotes.value = true;
}

function submitDone() {
    submitting.value = true;
    router.post(`/tasks/${props.task.id}/done`, {
        completion_notes: notes.value || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
 submitting.value = false; 
},
    });
}

function goBack() {
 window.history.back(); 
}
</script>

<template>
    <Head :title="task.title" />

    <div class="mx-auto max-w-3xl py-6">
        <button class="mb-4 flex items-center gap-1 text-xs text-blue-600 hover:text-blue-700" @click="goBack">
            <ArrowLeft class="h-4 w-4" /> Back to board
        </button>

        <div class="overflow-hidden rounded-xl border border-blue-200 bg-white shadow-sm dark:border-blue-800 dark:bg-gray-800">
            <!-- Projects heading + project tab -->
            <div class="flex items-center gap-2 border-b border-blue-100 bg-blue-50/50 px-6 py-3 dark:border-blue-800 dark:bg-blue-900/20">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Projects</span>
                <span
                    class="inline-flex items-center rounded-md px-2.5 py-0.5 text-xs font-medium"
                    :class="task.status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-blue-100 text-blue-700'"
                    :style="task.project.color ? { backgroundColor: task.project.color + '20', color: task.project.color } : {}"
                >
                    <span class="mr-1 inline-block h-1.5 w-1.5 rounded-full" :style="{ backgroundColor: task.project.color || '#3B82F6' }" />
                    {{ task.project.title }}
                </span>
            </div>

            <!-- Phase indicator based on status -->
            <div v-if="task.status === 'completed'" class="border-b border-green-100 bg-green-50 px-6 py-3 dark:border-green-800 dark:bg-green-900/20">
                <div class="flex items-center gap-2 text-sm font-medium text-green-700 dark:text-green-300">
                    <CheckCircle2 class="h-5 w-5" />
                    Task completed
                    <span v-if="task.completion_notes">— with notes</span>
                </div>
            </div>

            <!-- Profile header (only when assigned) -->
            <div v-if="task.project_member" class="flex items-center gap-3 border-b border-blue-100 px-6 py-4 dark:border-blue-800">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-800 dark:text-blue-200">
                    {{ task.project_member.user.name.charAt(0).toUpperCase() }}
                </div>
                <div>
                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ task.project_member.user.name }}</div>
                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ task.project_member.role || 'Member' }}</div>
                </div>
            </div>

            <!-- Task title + deadline -->
            <div class="border-b border-blue-100 px-6 py-4 dark:border-blue-800">
                <h1 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ task.title }}</h1>
                <div v-if="task.deadline" class="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                    <Calendar class="h-3.5 w-3.5" />
                    Deadline: <span class="font-medium">{{ task.deadline }}</span>
                </div>
            </div>

            <!-- Files -->
            <div v-if="task.file_path" class="flex items-center justify-between border-b border-blue-100 px-6 py-3 dark:border-blue-800">
                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">File: {{ fileName }}</span>
                <a
                    :href="'/storage/' + task.file_path"
                    target="_blank"
                    class="flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline"
                >
                    <component :is="fileIcon(task.file_type)" class="h-4 w-4" />
                    View
                </a>
            </div>

            <!-- Description -->
            <div class="border-b border-blue-100 px-6 py-4 dark:border-blue-800">
                <h2 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Descriptions:</h2>
                <div v-if="task.description" class="text-sm leading-relaxed text-gray-600 dark:text-gray-400" v-html="task.description" />
                <p v-else class="text-sm italic text-gray-400">No description.</p>
            </div>

            <!-- Tasking (checklist) -->
            <div class="border-b border-blue-100 px-6 py-4 dark:border-blue-800">
                <h2 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Task:</h2>
                <div v-if="!task.checklist_items?.length" class="text-sm italic text-gray-400">No tasking items.</div>
                <div v-else class="flex flex-col gap-2.5">
                    <div v-for="item in task.checklist_items" :key="item.id" class="flex items-center gap-3">
                        <input
                            type="checkbox"
                            :checked="item.is_completed"
                            :disabled="task.status !== 'accepted'"
                            class="h-4 w-4 accent-blue-600 disabled:cursor-not-allowed disabled:opacity-50"
                            @change="toggleChecklist(item)"
                        />
                        <span class="text-sm" :class="item.is_completed ? 'text-gray-400 line-through' : 'text-gray-700 dark:text-gray-300'">{{ item.title }}</span>
                    </div>
                </div>
            </div>

            <!-- Completion notes (shown when completed) -->
            <div v-if="task.completion_notes && task.status === 'completed'" class="border-b border-blue-100 px-6 py-4 dark:border-blue-800">
                <h2 class="mb-2 text-sm font-semibold text-gray-700 dark:text-gray-300">Completion notes:</h2>
                <p class="text-sm leading-relaxed text-gray-600 dark:text-gray-400">{{ task.completion_notes }}</p>
            </div>

            <!-- Mark as Done / Submit flow -->
            <div v-if="allDone && task.status === 'accepted'" class="border-t border-blue-100 px-6 py-4 dark:border-blue-800">
                <!-- Note textarea (appears after clicking Mark as Done) -->
                <div v-if="showNotes" class="mb-4">
                    <label class="mb-1.5 block text-xs font-medium text-gray-600 dark:text-gray-400">Notes (optional)</label>
                    <textarea
                        v-model="notes"
                        rows="3"
                        placeholder="Add any completion notes..."
                        class="w-full rounded-lg border border-blue-200 bg-white px-3 py-2 text-sm outline-none focus:border-blue-400 dark:border-blue-800 dark:bg-gray-800"
                    />
                </div>

                <button
                    v-if="!showNotes"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 active:scale-[0.98]"
                    @click="markDone"
                >
                    <CheckCircle2 class="h-5 w-5" />
                    Mark as Done
                </button>

                <button
                    v-if="showNotes"
                    class="flex w-full items-center justify-center gap-2 rounded-lg bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 active:scale-[0.98] disabled:opacity-60"
                    :disabled="submitting"
                    @click="submitDone"
                >
                    <CheckCircle2 class="h-5 w-5" />
                    {{ submitting ? 'Submitting...' : 'Submit' }}
                </button>
            </div>
        </div>
    </div>
</template>
