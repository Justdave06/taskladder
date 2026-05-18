<script setup lang="ts">
import { Head, router, Link, usePage } from '@inertiajs/vue3';
import {
    Plus, X, Circle, FileText, FileSpreadsheet, File, Search, Upload,
    Pin, PinOff, GripVertical, Calendar, ChevronDown,
} from 'lucide-vue-next';
import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue';
import ChatBox from '@/components/ChatBox.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface ChecklistItem { id: number; title: string; is_completed: boolean }

interface NoteData {
    id: number; project_id: number; content: string;
    color: string | null; position: number; created_by: number;
}

interface MessageData {
    id: number; project_id: number; user_id: number;
    content: string; created_at: string;
    user: { id: number; name: string; email: string };
}

interface TaskItemData {
    id: number; title: string; description: string | null;
    deadline: string | null; priority: string; file_type: string | null;
    project_id: number; project_member_id: number | null;
    status: string; pinned: boolean;
    checklist_items: ChecklistItem[]; file_path: string | null;
    project_member: { id: number; user_id: number; user: { id: number; name: string; email: string } } | null;
    creator: { id: number; name: string } | null;
}

interface Member {
    id: number; project_id: number; user_id: number;
    status: string; role: string | null; role_id: number | null;
    user: { id: number; name: string; email: string; last_active_at?: string | null };
    task_items: TaskItemData[];
}

interface RoleData {
    id: number; project_id: number; name: string;
}

interface ProjectData {
    id: number; title: string; status: string;
    color: string | null; created_by: number; members: Member[];
    roles: RoleData[];
}

interface PageProps {
    projects: ProjectData[]; unassignedTasks: TaskItemData[];
    notes: Record<string, NoteData[]>;
    messages: Record<string, MessageData[]>;
}

const page = usePage();
const pageProps = page.props as unknown as PageProps;
const authUser = page.props.auth.user as { id: number; name: string };

const projects = ref(pageProps.projects);
const unassignedTasks = ref(pageProps.unassignedTasks);
const notesMap = ref(pageProps.notes || {});
const messagesMap = ref(pageProps.messages || {});
const optimisticMessages = ref<MessageData[]>([]);

const dragId = ref<number | null>(null);
const filterTab = ref('all');
const searchQuery = ref('');
const activeProjectTab = ref(projects.value[0]?.id ?? null);
const showCreateModal = ref(false);
const showRoleDialog = ref(false);

const createForm = ref({
    title: '', description: '', deadline: '', priority: '', file_type: '',
    project_id: activeProjectTab.value ?? projects.value[0]?.id ?? null,
});
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const pendingSubs = ref<{ title: string }[]>([]);
const subInput = ref('');

const avatarColors = ['bg-blue-100 text-blue-700', 'bg-green-100 text-green-700', 'bg-amber-100 text-amber-700', 'bg-purple-100 text-purple-700'];
const priorityLabels: Record<string, string> = { high: 'High', med: 'Medium', low: 'Low', unimportant: 'Unimportant' };
const priorityDots: Record<string, string> = { high: 'bg-red-500', med: 'bg-amber-500', low: 'bg-yellow-500', unimportant: 'bg-gray-400' };
const today = computed(() => new Date().toISOString().split('T')[0]);

const activeProject = computed(() => projects.value.find((p) => p.id === activeProjectTab.value));
const isBoss = computed(() => activeProject.value?.created_by === authUser.id);

const allUnassigned = computed(() =>
    unassignedTasks.value.filter((t) =>
        (filterTab.value === 'all' || t.file_type === filterTab.value) &&
        (t.title.toLowerCase().includes(searchQuery.value.toLowerCase())),
    ),
);

const sidebarGroups = computed(() => {
    const pinned = allUnassigned.value.filter((t) => t.pinned);
    const rest = allUnassigned.value.filter((t) => !t.pinned);

    return [
        { label: 'Pinned', key: 'pinned', dot: '', items: pinned },
        { label: 'High', key: 'high', dot: 'bg-red-500', items: rest.filter((t) => t.priority === 'high') },
        { label: 'Medium', key: 'med', dot: 'bg-amber-500', items: rest.filter((t) => t.priority === 'med') },
        { label: 'Low', key: 'low', dot: 'bg-yellow-500', items: rest.filter((t) => t.priority === 'low') },
        { label: 'Unimportant', key: 'unimportant', dot: 'bg-gray-400', items: rest.filter((t) => t.priority === 'unimportant') },
    ].filter((g) => g.items.length > 0);
});

const priorityCounts = computed(() => {
    const allTasks = [
        ...unassignedTasks.value,
        ...(activeProject.value?.members.flatMap((m) => m.task_items) || []),
    ];

    return {
        high: allTasks.filter((t) => t.priority === 'high').length,
        med: allTasks.filter((t) => t.priority === 'med').length,
        low: allTasks.filter((t) => t.priority === 'low').length,
        unimportant: allTasks.filter((t) => t.priority === 'unimportant').length,
    };
});

// Notes
const activeNotes = computed(() =>
    (notesMap.value[String(activeProjectTab.value ?? '')] || [])
        .slice()
        .sort((a, b) => a.position - b.position),
);
const noteColors = ['#FEEBC8', '#BEE3F8', '#C6F6D5', '#FDF4FF', '#E0E7FF'];
const noteDragId = ref<number | null>(null);
const editingNoteId = ref<number | null>(null);
const editingNoteContent = ref('');
const newNoteContent = ref('');
const showNewNote = ref(false);

const showNotesSection = ref(true);
const showPrioritySection = ref(true);

const chatContacts = computed(() =>
    activeProject.value?.members?.filter(m => m.user_id !== authUser.id) || [],
);

const allMessages = computed(() => {
    const server = messagesMap.value[String(activeProjectTab.value ?? '')] || [];
    const serverIds = new Set(server.map(m => m.id));
    const local = optimisticMessages.value.filter(m => !serverIds.has(m.id));

    return [...server, ...local];
});

function sendMessage(text: string) {
    if (!text.trim() || !activeProjectTab.value) {
return;
}

    const pid = activeProjectTab.value;
    const tempId = -(Date.now() + Math.random());
    optimisticMessages.value.push({
        id: tempId, project_id: pid, user_id: authUser.id,
        content: text, created_at: new Date().toISOString(),
        user: { id: authUser.id, name: authUser.name, email: '' },
    });
    const csrfMatch = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    fetch('/messages', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': csrfMatch ? decodeURIComponent(csrfMatch[1]) : '',
        },
        body: JSON.stringify({ project_id: pid, content: text }),
    }).catch(() => {
        optimisticMessages.value = optimisticMessages.value.filter(m => m.id !== tempId);
    });
}

function isActive(user: { last_active_at?: string | null }): boolean {
    if (!user.last_active_at) {
return false;
}

    const diff = Date.now() - new Date(user.last_active_at).getTime();

    return diff < 120_000;
}

let pollInterval: ReturnType<typeof setInterval>;

onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ only: ['projects', 'unassignedTasks', 'notes', 'messages'] });
    }, 2000);
});

onUnmounted(() => {
    if (pollInterval) {
clearInterval(pollInterval);
}
});

watch(
    () => [page.props.projects, page.props.unassignedTasks, page.props.notes, page.props.messages],
    ([newProjects, newTasks, newNotes, newMessages]) => {
        const p = newProjects as ProjectData[];
        projects.value = p;
        unassignedTasks.value = newTasks as TaskItemData[];
        notesMap.value = (newNotes as Record<string, NoteData[]>) || {};
        messagesMap.value = (newMessages as Record<string, MessageData[]>) || {};

        if (!activeProjectTab.value || !p.some((proj) => proj.id === activeProjectTab.value)) {
            activeProjectTab.value = p[0]?.id ?? null;
        }
    },
);

function memberTasks(member: Member) {
    if (isBoss.value) {
return member.task_items || [];
}

    return (member.task_items || []).filter((t) => t.status === 'accepted' || t.status === 'completed');
}

function memberProgress(member: Member): number {
    const items = member.task_items?.flatMap((t) => t.checklist_items) || [];

    if (!items.length) {
return 0;
}

    return Math.round((items.filter((i) => i.is_completed).length / items.length) * 100);
}
function progressColor(pct: number) {
    return pct >= 70 ? '#16A34A' : pct >= 40 ? '#D97706' : '#DC2626';
}
function taskProgress(task: TaskItemData): number {
    const items = task.checklist_items;

    if (!items?.length) {
return 0;
}

    return Math.round((items.filter((i) => i.is_completed).length / items.length) * 100);
}

const currentMember = computed(() =>
    activeProject.value?.members.find((m) => m.user_id === authUser.id) ?? null,
);

const myTasks = computed(() =>
    (currentMember.value?.task_items || []).filter((t) => t.status === 'accepted' || t.status === 'completed'),
);

const doneToday = computed(() =>
    (currentMember.value?.task_items || []).filter((t) => t.status === 'completed').length,
);

const activeProgTab = ref(0);
const activeViewTab = ref<'mine' | 'all'>('mine');

function canMarkDone(task: TaskItemData): boolean {
    return task.checklist_items.length === 0 || task.checklist_items.every((i) => i.is_completed);
}

const memberViewTasks = computed(() => {
    if (!activeProject.value) {
return [];
}

    if (activeViewTab.value === 'all') {
        return activeProject.value.members.flatMap((m) => m.task_items);
    }

    return myTasks.value;
});

const fileAcceptAttr = computed(() => {
    const t = createForm.value.file_type;

    if (t === 'DOC') {
return '.doc,.docx,.DOC,.DOCX';
}

    if (t === 'PDF') {
return '.pdf,.PDF';
}

    if (t === 'XLS') {
return '.xls,.xlsx,.XLS,.XLSX';
}

    return '*/*';
});

function markDone(task: TaskItemData) {
    router.post(`/tasks/${task.id}/done`, { completion_notes: '' }, { preserveScroll: true });
}

function handleTaskFileUpload(e: Event, taskId: number) {
    const input = e.target as HTMLInputElement;

    if (!input.files?.[0]) {
return;
}

    const formData = new FormData();
    formData.append('file', input.files[0]);
    router.post(`/tasks/${taskId}/file`, formData, { preserveScroll: true });
}

function isCurrentUser(member: Member) {
 return member.user_id === authUser.id; 
}
function isMemberActive(member: Member) {
 return member.status === 'accepted' || member.status === 'completed'; 
}

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

const tabStyles: Record<string, { active: string; inactive: string }> = {
    all: { active: 'bg-blue-600 text-white', inactive: 'bg-blue-50 text-blue-600 hover:bg-blue-100' },
    DOC: { active: 'bg-blue-600 text-white', inactive: 'bg-blue-50 text-blue-600 hover:bg-blue-100' },
    PDF: { active: 'bg-red-600 text-white', inactive: 'bg-red-50 text-red-600 hover:bg-red-100' },
    XLS: { active: 'bg-green-600 text-white', inactive: 'bg-green-50 text-green-600 hover:bg-green-100' },
};

function onDragStart(taskId: number) {
    if (!isBoss.value) {
return;
}

    dragId.value = taskId;
}
function onDragEnd() {
 dragId.value = null; 
}
function onDragOver(e: DragEvent) {
 e.preventDefault(); (e.currentTarget as HTMLElement).classList.add('ring-2', 'ring-blue-400'); 
}
function onDragLeave(e: DragEvent) {
 (e.currentTarget as HTMLElement).classList.remove('ring-2', 'ring-blue-400'); 
}
function onDrop(e: DragEvent, memberId: number) {
    e.preventDefault();
    (e.currentTarget as HTMLElement).classList.remove('ring-2', 'ring-blue-400');

    if (!dragId.value) {
return;
}

    router.post(`/tasks/${dragId.value}/assign`, { project_member_id: memberId }, { preserveScroll: true, onSuccess: () => {
 dragId.value = null; 
} });
}

function togglePin(task: TaskItemData) {
    router.post(`/tasks/${task.id}/pin`, {}, { preserveScroll: true });
}

function assignMemberRole(memberId: number, roleId: string) {
    router.patch(`/manage/members/${memberId}/role`, { role_id: roleId ? Number(roleId) : null }, { preserveScroll: true });
}

function acceptTask(taskId: number) {
 router.post(`/tasks/${taskId}/accept`, {}, { preserveScroll: true }); 
}
function declineTask(taskId: number) {
 router.post(`/tasks/${taskId}/decline`, {}, { preserveScroll: true }); 
}
function unassignTask(taskId: number) {
 router.post(`/tasks/${taskId}/unassign`, {}, { preserveScroll: true }); 
}
function toggleChecklist(item: ChecklistItem) {
    router.patch(`/checklist-items/${item.id}`, { is_completed: !item.is_completed }, { preserveScroll: true });
}

function handleFileChange(e: Event) {
    const input = e.target as HTMLInputElement;

    if (!input.files?.[0]) {
return;
}

    const file = input.files[0];
    const name = file.name.toLowerCase();

    if (createForm.value.file_type === 'DOC' && !name.endsWith('.doc') && !name.endsWith('.docx')) {
 input.value = '';

 return alert('Only DOC/DOCX files allowed.'); 
}

    if (createForm.value.file_type === 'PDF' && !name.endsWith('.pdf')) {
 input.value = '';

 return alert('Only PDF files allowed.'); 
}

    if (createForm.value.file_type === 'XLS' && !name.endsWith('.xls') && !name.endsWith('.xlsx')) {
 input.value = '';

 return alert('Only XLS/XLSX files allowed.'); 
}

    selectedFile.value = file;

    if (name.endsWith('.doc') || name.endsWith('.docx')) {
createForm.value.file_type = 'DOC';
} else if (name.endsWith('.pdf')) {
createForm.value.file_type = 'PDF';
} else if (name.endsWith('.xls') || name.endsWith('.xlsx')) {
createForm.value.file_type = 'XLS';
}
}

function addPendingSub() {
    const t = subInput.value.trim();

    if (!t) {
return;
}

    pendingSubs.value.push({ title: t });
    subInput.value = '';
}
function removePendingSub(i: number) {
 pendingSubs.value.splice(i, 1); 
}

function createTask() {
    const missing = [];

    if (!createForm.value.title.trim()) {
missing.push('Task name');
}

    if (!createForm.value.priority) {
missing.push('Priority');
}

    if (!createForm.value.deadline) {
missing.push('Deadline');
}

    if (missing.length) {
        alert('Please fill in: ' + missing.join(', '));

        return;
    }

    const formData = new FormData();
    formData.append('title', createForm.value.title);
    formData.append('description', createForm.value.description);
    formData.append('deadline', createForm.value.deadline || '');
    formData.append('priority', createForm.value.priority);
    formData.append('file_type', createForm.value.file_type || '');
    formData.append('project_id', String(createForm.value.project_id));
    pendingSubs.value.forEach((s, i) => formData.append(`checklist[${i}][title]`, s.title));

    if (selectedFile.value) {
formData.append('file', selectedFile.value);
}

    router.post('/tasks', formData, {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.value = { title: '', description: '', deadline: '', priority: '', file_type: '', project_id: activeProjectTab.value ?? projects.value[0]?.id ?? null };
            pendingSubs.value = [];
            selectedFile.value = null;
        },
        onError: (errors) => {
            const msgs = Object.values(errors).join('\n');
            alert(msgs || 'Failed to save task.');
        },
    });
}

// Notes
function startEditNote(note: NoteData) {
    editingNoteId.value = note.id;
    editingNoteContent.value = note.content;
    nextTick(() => {
        const el = document.querySelector(`[data-note-edit="${note.id}"]`) as HTMLTextAreaElement;
        el?.focus();
    });
}
function saveEditNote(note: NoteData) {
    if (!editingNoteContent.value.trim()) {
return;
}

    router.patch(`/notes/${note.id}`, { content: editingNoteContent.value }, {
        preserveScroll: true,
        onSuccess: () => {
 editingNoteId.value = null; editingNoteContent.value = ''; 
},
    });
}
function cancelEditNote() {
 editingNoteId.value = null; editingNoteContent.value = ''; 
}
function deleteNote(note: NoteData) {
    if (!confirm('Delete this note?')) {
return;
}

    router.delete(`/notes/${note.id}`, { preserveScroll: true });
}
function addNote() {
    if (!newNoteContent.value.trim() || !activeProjectTab.value) {
return;
}

    const randomColor = noteColors[Math.floor(Math.random() * noteColors.length)];
    router.post('/notes', {
        project_id: activeProjectTab.value,
        content: newNoteContent.value,
        color: randomColor,
    }, {
        preserveScroll: true,
        onSuccess: () => {
 newNoteContent.value = ''; showNewNote.value = false; 
},
    });
}
function onNoteDragStart(note: NoteData) {
 noteDragId.value = note.id; 
}
function onNoteDragOver(e: DragEvent, target: NoteData) {
    e.preventDefault();

    if (noteDragId.value === null || noteDragId.value === target.id) {
return;
}

    const notes = activeNotes.value;
    const fromIdx = notes.findIndex((n) => n.id === noteDragId.value);
    const toIdx = notes.findIndex((n) => n.id === target.id);

    if (fromIdx === -1 || toIdx === -1) {
return;
}

    const reordered = notes.map((n, i) => ({
        id: n.id,
        position: i === fromIdx ? toIdx : i === toIdx ? fromIdx : i < Math.min(fromIdx, toIdx) || i > Math.max(fromIdx, toIdx) ? i : i,
    }));
    router.post('/notes/reorder', { notes: reordered }, { preserveScroll: true });
    noteDragId.value = null;
}
</script>

<template>
    <Head title="Desk" />

<template v-if="isBoss">
    <div class="flex min-h-[calc(100vh-5rem)] gap-0">
        <!-- LEFT: Task sidebar -->
        <aside class="hidden w-60 shrink-0 border-r border-[#E4E7F0] bg-white lg:flex lg:flex-col">
            <div class="flex items-center justify-between border-b border-[#E4E7F0] px-4 py-[18px]">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">Tasks</span>
                <button v-if="isBoss" class="flex h-7 w-7 items-center justify-center rounded-lg bg-[#2563EB] text-sm text-white transition hover:bg-[#1d4ed8] active:scale-95" @click="showCreateModal = true">+</button>
            </div>

            <!-- Filter pills -->
            <div class="flex items-center gap-0.5 border-b border-[#E4E7F0] px-3 py-2.5">
                <button v-for="tab in ['all', 'DOC', 'PDF', 'XLS']" :key="tab" class="rounded-md px-2 py-1 text-[11px] font-medium transition-colors" :class="filterTab === tab ? tabStyles[tab].active : tabStyles[tab].inactive" @click="filterTab = tab">
                    {{ tab === 'all' ? 'All' : tab }}
                </button>
                <Search class="ml-auto h-4 w-4 text-[#9BA3B8]" />
            </div>

            <!-- Task list -->
            <div class="flex-1 overflow-y-auto px-2.5 py-3">
                <div v-if="sidebarGroups.length === 0" class="px-2 py-12 text-center text-xs text-[#9BA3B8]">
                    No unassigned tasks
                </div>
                <template v-for="group in sidebarGroups" :key="group.key">
                    <div class="flex items-center gap-1.5 px-1 py-1.5">
                        <span v-if="group.dot" class="inline-block h-1.5 w-1.5 rounded-full" :class="group.dot" />
                        <svg v-else class="h-3 w-3 text-[#9BA3B8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L15.09 8.26L22 9.27L17 14.14L18.18 21.02L12 17.77L5.82 21.02L7 14.14L2 9.27L8.91 8.26L12 2Z" /></svg>
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-[#9BA3B8]">{{ group.label }}</span>
                        <span class="text-[10px] font-normal text-[#9BA3B8]">({{ group.items.length }})</span>
                    </div>
                    <div
                        v-for="task in group.items"
                        :key="task.id"
                        :draggable="isBoss"
                        class="mb-1 cursor-grab rounded-lg border border-[#E4E7F0] bg-white px-3 py-2.5 transition-all hover:border-[#c5ccde] hover:shadow-sm active:cursor-grabbing"
                        :class="{ 'opacity-40': dragId === task.id, 'border-l-[3px] border-l-[#2563EB]': task.pinned }"
                        @dragstart="onDragStart(task.id)"
                        @dragend="onDragEnd"
                    >
                        <div class="text-[13px] font-medium text-[#0F1623]">{{ task.title }}</div>
                        <div class="flex items-center justify-between">
                            <span class="font-mono text-[11px] text-[#9BA3B8]">{{ task.file_type || 'Task' }}</span>
                            <div class="flex items-center gap-2">
                                <span v-if="task.deadline" class="flex items-center gap-1 text-[10px] text-[#9BA3B8]">
                                    <Calendar class="h-3 w-3" />{{ task.deadline }}
                                </span>
                                <button class="text-[#9BA3B8] hover:text-[#2563EB]" @click.stop="togglePin(task)" :title="task.pinned ? 'Unpin' : 'Pin'">
                                    <Pin v-if="task.pinned" class="h-3 w-3 fill-[#2563EB] text-[#2563EB]" />
                                    <PinOff v-else class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                    </template>
                </div>
        </aside>

        <!-- CENTER: Team board -->
        <main class="flex flex-1 flex-col overflow-hidden bg-[#F7F8FC]">
            <!-- Search + filter row (mobile) -->
            <div class="flex items-center gap-2 border-b border-[#E4E7F0] bg-white px-4 py-2.5 lg:hidden">
                <div class="flex gap-1">
                    <button v-for="tab in ['all', 'DOC', 'PDF', 'XLS']" :key="tab" class="rounded-md px-2 py-1 text-[11px] font-medium transition-colors" :class="filterTab === tab ? tabStyles[tab].active : tabStyles[tab].inactive" @click="filterTab = tab">
                        {{ tab === 'all' ? 'All' : tab }}
                    </button>
                </div>
                <div class="relative ml-auto max-w-[140px]">
                    <Search class="absolute left-2 top-1/2 h-3 w-3 -translate-y-1/2 text-[#9BA3B8]" />
                    <input v-model="searchQuery" type="text" placeholder="Search..." class="w-full rounded-md border border-[#E4E7F0] bg-white py-1 pl-7 pr-2 text-xs outline-none focus:border-[#2563EB]" />
                </div>
            </div>

            <div class="flex flex-1 gap-4 overflow-y-auto p-4 lg:p-5">
                <!-- Left column: Team section -->
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <!-- Team card -->
                    <div class="overflow-hidden rounded-2xl border border-[#E4E7F0] bg-white">
                        <template v-if="projects.length > 0">
                            <div class="flex items-center justify-between border-b border-[#E4E7F0] px-4 py-[18px]">
                                <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">PROJECT • {{ activeProject?.title || '—' }}</span>
                            </div>
                            <!-- Project tabs + Roles -->
                            <div class="flex items-center gap-2.5 px-4 pt-3.5">
                                <button v-for="p in projects" :key="p.id" class="rounded-full px-3.5 py-1.5 text-[13px] font-medium transition-all" :class="activeProjectTab === p.id ? 'bg-[#0F1623] text-white' : 'bg-transparent text-[#9BA3B8] hover:bg-[#F0F2F8]'" @click="activeProjectTab = p.id">
                                    {{ p.title }}
                                </button>
                                <button v-if="isBoss" class="ml-auto flex items-center gap-1 rounded-md border border-[#E4E7F0] px-2.5 py-1 text-[11px] font-medium text-[#9BA3B8] transition hover:bg-[#F0F2F8]" @click="showRoleDialog = true">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                                    Select Roles
                                </button>
                            </div>

                            <!-- Member cards -->
                            <div class="grid grid-cols-2 gap-2 px-3 py-3 md:grid-cols-3 xl:grid-cols-4">
                                <div v-for="(member, idx) in activeProject?.members || []" :key="member.id" :data-member="member.user.name" class="relative flex flex-col items-center rounded-lg border border-[#E4E7F0] px-3 py-3 text-center transition-all hover:border-[#b0bdd8] hover:bg-[#EEF3FF] hover:shadow-sm" @dragover="onDragOver" @dragleave="onDragLeave" @drop="(e) => onDrop(e, member.id)">
                                    <div class="relative mb-2 flex h-11 w-11 items-center justify-center rounded-full text-[15px] font-semibold" :class="avatarColors[idx % avatarColors.length]">
                                        {{ member.user.name.charAt(0).toUpperCase() }}
                                        <span class="absolute bottom-0.5 right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white bg-green-500" />
                                    </div>
                                    <div class="text-[12px] font-semibold text-[#0F1623]">{{ member.user.name }}</div>
                                    <div class="text-[10px] text-[#9BA3B8]">{{ (activeProject?.roles?.find(r => r.id === member.role_id)?.name) || member.role || 'Member' }}</div>
                                    <div class="mt-1 text-[10px] font-medium text-[#2563EB]">
                                        Tasks: <span class="font-semibold">{{ member.task_items?.length || 0 }}</span>
                                    </div>
                                    <div class="mt-2 flex flex-wrap justify-center gap-1">
                                        <span v-for="t in (member.task_items?.filter(ti => ti.status !== 'completed') || []).slice(0, 4)" :key="t.id" class="max-w-[70px] truncate rounded-full bg-[#EEF3FF] px-2 py-0.5 text-[9px] font-medium text-[#2563EB]">{{ t.title }}</span>
                                    </div>
                                    <div v-if="isBoss" class="mt-1 text-[10px] text-[#2563EB] opacity-0 transition-opacity group-hover:opacity-100">
                                        <Plus class="mr-0.5 inline h-3 w-3" /> Drop here
                                    </div>
                                </div>
                            </div>

                            <!-- Progress -->
                            <div class="border-t border-[#E4E7F0] px-4 py-3">
                                <div class="mb-2.5 text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">Progress</div>
                                <div v-for="(member, idx) in activeProject?.members || []" :key="'prog-' + member.id" class="mb-2 flex items-center gap-2.5">
                                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold" :class="avatarColors[idx % avatarColors.length]">
                                        {{ member.user.name.charAt(0).toUpperCase() }}
                                    </div>
                                    <span class="w-9 text-xs font-medium text-[#5A6278]">{{ member.user.name }}</span>
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-[#F0F2F8]">
                                        <div class="h-full rounded-full transition-all duration-500" :style="{ width: memberProgress(member) + '%', backgroundColor: progressColor(memberProgress(member)) }" />
                                    </div>
                                    <span class="w-8 text-right text-[11px] font-medium text-[#5A6278]">{{ memberProgress(member) }}%</span>
                                    <Link v-if="member.task_items?.length" :href="`/tasks/${member.task_items[0].id}/view`" class="ml-1 cursor-pointer text-[11px] font-medium text-[#2563EB]">view</Link>
                                </div>
                            </div>
                        </template>
                        <div v-else class="flex flex-col items-center justify-center px-6 py-16 text-center">
                            <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">No projects yet</span>
                            <p class="mt-1 text-[12px] text-[#9BA3B8]">Create a project from the Manage tab to get started.</p>
                        </div>
                    </div>
                </div>

                <!-- RIGHT: Notes + Priority + Chat -->
                <aside class="hidden w-[260px] shrink-0 flex-col gap-4 xl:flex">
                    <!-- Notes (collapsible) -->
                    <div class="rounded-2xl border border-[#E4E7F0] bg-white">
                        <div class="flex cursor-pointer items-center justify-between px-3.5 py-3 select-none" @click="showNotesSection = !showNotesSection">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">Notes</span>
                            <ChevronDown class="h-3.5 w-3.5 text-[#9BA3B8] transition-transform" :class="showNotesSection ? 'rotate-0' : '-rotate-90'" />
                        </div>
                        <div v-if="showNotesSection" class="border-t border-[#E4E7F0] p-3.5 pt-2.5">
                            <div class="flex flex-col gap-1.5">
                                <div v-if="showNewNote" class="flex flex-col gap-1">
                                    <textarea v-model="newNoteContent" rows="2" placeholder="Write a note…" class="w-full resize-none rounded-lg border border-[#E4E7F0] bg-white p-2 text-[11px] outline-none focus:border-[#2563EB]" @keydown.ctrl.enter="addNote" @keydown.escape="showNewNote = false; newNoteContent = ''" />
                                    <div class="flex gap-1.5">
                                        <button class="rounded-md bg-[#2563EB] px-3 py-1 text-[10px] font-medium text-white hover:bg-[#1d4ed8]" @click="addNote">Save</button>
                                        <button class="rounded-md border border-[#E4E7F0] px-3 py-1 text-[10px] text-[#5A6278] hover:bg-[#F0F2F8]" @click="showNewNote = false; newNoteContent = ''">Cancel</button>
                                    </div>
                                </div>
                                <button v-if="isBoss && !showNewNote" class="mb-1 self-start text-[10px] font-medium text-[#2563EB] hover:underline" @click="showNewNote = true">+ Add</button>
                                <div v-for="note in activeNotes" :key="note.id" :draggable="isBoss" class="group relative cursor-grab rounded-lg p-2.5 text-[11px] font-medium leading-snug text-[#0F1623]/70 active:cursor-grabbing" :style="{ backgroundColor: note.color || '#FEEBC8' }" @dragstart="onNoteDragStart(note)" @dragover="(e) => onNoteDragOver(e, note)">
                                    <textarea v-if="editingNoteId === note.id" :data-note-edit="note.id" v-model="editingNoteContent" rows="2" class="w-full resize-none rounded bg-white/60 p-1 text-[11px] outline-none" @blur="saveEditNote(note)" @keydown.ctrl.enter="saveEditNote(note)" @keydown.escape="cancelEditNote" />
                                    <div v-else class="flex items-start gap-1.5">
                                        <GripVertical class="mt-0.5 h-3 w-3 shrink-0 text-[#0F1623]/30 opacity-0 transition-opacity group-hover:opacity-100" />
                                        <span class="flex-1 whitespace-pre-wrap break-words">{{ note.content }}</span>
                                    </div>
                                    <div v-if="isBoss" class="absolute right-1.5 top-1.5 flex gap-0.5 opacity-0 transition-opacity group-hover:opacity-100">
                                        <button class="rounded p-0.5 text-[10px] text-[#5A6278] hover:bg-black/10" title="Edit" @click="startEditNote(note)">✎</button>
                                        <button class="rounded p-0.5 text-[10px] text-red-500 hover:bg-black/10" title="Delete" @click="deleteNote(note)">✕</button>
                                    </div>
                                </div>
                                <div v-if="activeNotes.length === 0 && !showNewNote" class="py-3 text-center text-[10px] text-[#9BA3B8]">No notes yet.</div>
                            </div>
                        </div>
                    </div>

                    <!-- Priority Dashboard (collapsible) -->
                    <div class="rounded-2xl border border-[#E4E7F0] bg-white">
                        <div class="flex cursor-pointer items-center justify-between px-3.5 py-3 select-none" @click="showPrioritySection = !showPrioritySection">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">Priority Dashboard</span>
                            <ChevronDown class="h-3.5 w-3.5 text-[#9BA3B8] transition-transform" :class="showPrioritySection ? 'rotate-0' : '-rotate-90'" />
                        </div>
                        <div v-if="showPrioritySection" class="border-t border-[#E4E7F0] p-3.5 pt-2.5">
                            <div class="grid grid-cols-2 gap-2">
                                <div class="rounded-lg border border-[#E4E7F0] p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5 text-[11px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-red-500" /> High</div>
                                    <div class="text-[22px] font-semibold text-[#0F1623]">{{ priorityCounts.high }}</div>
                                </div>
                                <div class="rounded-lg border border-[#E4E7F0] p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5 text-[11px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500" /> Medium</div>
                                    <div class="text-[22px] font-semibold text-[#0F1623]">{{ priorityCounts.med }}</div>
                                </div>
                                <div class="rounded-lg border border-[#E4E7F0] p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5 text-[11px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-yellow-500" /> Low</div>
                                    <div class="text-[22px] font-semibold text-[#0F1623]">{{ priorityCounts.low }}</div>
                                </div>
                                <div class="rounded-lg border border-[#E4E7F0] p-2.5">
                                    <div class="mb-1 flex items-center gap-1.5 text-[11px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-gray-400" /> Unimp.</div>
                                    <div class="text-[22px] font-semibold text-[#0F1623]">{{ priorityCounts.unimportant }}</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <ChatBox
                        :messages="allMessages"
                        :contacts="chatContacts"
                        :current-user-id="authUser.id"
                        :active-project-title="activeProject?.title"
                        @send="sendMessage"
                    />
                </aside>
            </div>
        </main>
    </div>
</template>

<template v-else>
    <div class="flex min-h-[calc(100vh-5rem)] gap-0">
        <!-- LEFT: Member list panel -->
        <aside class="hidden w-52 shrink-0 border-r border-[#E4E7F0] bg-white lg:flex lg:flex-col">
            <div class="border-b border-[#E4E7F0] px-4 py-[18px]">
                <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">PROJECT • {{ activeProject?.title || '—' }}</span>
            </div>
            <div class="flex-1 overflow-y-auto px-3 py-3">
                <div v-for="(member, idx) in activeProject?.members || []" :key="member.id" class="mb-2 flex items-center gap-2.5 rounded-lg px-2.5 py-2 transition hover:bg-[#F0F2F8]">
                    <div class="relative shrink-0">
                        <div class="flex h-8 w-8 items-center justify-center rounded-full text-[11px] font-semibold" :class="avatarColors[idx % avatarColors.length]">
                            {{ member.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <span v-if="member.user_id !== authUser.id" class="absolute -bottom-0.5 -right-0.5 h-2.5 w-2.5 rounded-full border-2 border-white" :class="isActive(member.user) ? 'bg-green-400' : 'bg-gray-300'" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[12px] font-medium text-[#0F1623] truncate">{{ member.user.name }}</div>
                        <div class="text-[10px] text-[#9BA3B8]">{{ (activeProject?.roles?.find(r => r.id === member.role_id)?.name) || member.role || 'Member' }}</div>
                    </div>
                    <span v-if="member.user_id === authUser.id" class="text-[9px] font-semibold text-[#2563EB]">YOU</span>
                </div>
            </div>
            <ChatBox
                :messages="allMessages"
                :contacts="chatContacts"
                :current-user-id="authUser.id"
                :active-project-title="activeProject?.title"
                @send="sendMessage"
            />
        </aside>

        <!-- CONTENT -->
        <main class="flex flex-1 flex-col overflow-hidden bg-[#F7F8FC]">
            <!-- Task tabs -->
            <div class="flex items-center gap-3 border-b border-[#E4E7F0] bg-white px-4 py-3">
                <button class="rounded-full px-4 py-1.5 text-[12px] font-medium transition-all" :class="activeViewTab === 'mine' ? 'bg-[#0F1623] text-white' : 'text-[#9BA3B8] hover:text-[#5A6278]'" @click="activeViewTab = 'mine'">My Tasks ({{ myTasks.length }})</button>
                <button class="rounded-full px-4 py-1.5 text-[12px] font-medium transition-all" :class="activeViewTab === 'all' ? 'bg-[#0F1623] text-white' : 'text-[#9BA3B8] hover:text-[#5A6278]'" @click="activeViewTab = 'all'">All ({{ activeProject?.members.flatMap(m => m.task_items).length || 0 }})</button>
            </div>

            <!-- Task cards grid -->
            <div class="flex-1 overflow-y-auto p-4">
                <div v-if="memberViewTasks.length === 0" class="flex flex-col items-center justify-center py-16 text-center">
                    <span class="text-[11px] font-semibold uppercase tracking-widest text-[#9BA3B8]">No tasks yet</span>
                    <p class="mt-1 text-[12px] text-[#9BA3B8]">Tasks assigned to you will appear here.</p>
                </div>
                <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <div v-for="task in memberViewTasks" :key="task.id" class="flex flex-col rounded-2xl border border-[#E4E7F0] bg-white p-4 transition hover:shadow-sm">
                        <!-- Header -->
                        <div class="mb-2 flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="text-[14px] font-semibold text-[#0F1623]">{{ task.title }}</div>
                                <div class="mt-0.5 flex items-center gap-2">
                                    <span v-if="task.deadline" class="flex items-center gap-1 text-[10px] text-[#9BA3B8]">
                                        <Calendar class="h-3 w-3" />{{ task.deadline }}
                                    </span>
                                    <span class="rounded bg-[#EEF3FF] px-1.5 py-0.5 text-[9px] font-medium text-[#2563EB]">{{ task.file_type || 'Task' }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="inline-block h-2 w-2 rounded-full" :class="priorityDots[task.priority] || 'bg-gray-400'" :title="priorityLabels[task.priority] || task.priority" />
                            </div>
                        </div>
                        <!-- Assignor -->
                        <div class="mb-2 flex items-center gap-1.5 text-[10px] text-[#9BA3B8]">
                            <span>by {{ task.project_member?.user?.name || task.creator?.name || 'System' }}</span>
                        </div>
                        <!-- Checklist -->
                        <div v-if="task.checklist_items?.length" class="mb-2 flex flex-col gap-1">
                            <div v-for="item in task.checklist_items" :key="item.id" class="flex items-center gap-2 text-[12px]">
                                <input type="checkbox" :checked="item.is_completed" class="h-3.5 w-3.5 cursor-pointer accent-[#2563EB]" @change="toggleChecklist(item)" />
                                <span :class="item.is_completed ? 'text-[#9BA3B8] line-through' : 'text-[#0F1623]'">{{ item.title }}</span>
                            </div>
                        </div>
                        <!-- Progress -->
                        <div v-if="task.checklist_items?.length" class="mb-2 flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-[#F0F2F8]">
                                <div class="h-full rounded-full transition-all" :style="{ width: taskProgress(task) + '%', backgroundColor: progressColor(taskProgress(task)) }" />
                            </div>
                            <span class="text-[10px] font-medium text-[#5A6278]">{{ taskProgress(task) }}%</span>
                        </div>
                        <!-- Actions -->
                        <div class="mt-auto flex items-center gap-2 pt-2">
                            <Button v-if="task.project_member_id === currentMember?.id && task.status === 'accepted'" size="sm" class="flex-1 bg-green-600 text-[11px] text-white hover:bg-green-700 disabled:opacity-40" :disabled="!canMarkDone(task)" @click="markDone(task)">
                                {{ canMarkDone(task) ? 'Mark Done' : 'Complete all to-dos first' }}
                            </Button>
                            <div v-if="task.file_type && task.project_member_id === currentMember?.id" class="relative">
                                <label class="flex cursor-pointer items-center gap-1 rounded-md border border-[#E4E7F0] px-2 py-1.5 text-[10px] text-[#5A6278] hover:bg-[#F0F2F8]">
                                    <Upload class="h-3 w-3" /> Upload
                                    <input type="file" :accept="'.' + task.file_type.toLowerCase() + ',.' + task.file_type.toLowerCase() + 'x'" class="absolute inset-0 cursor-pointer opacity-0" @change="(e) => handleTaskFileUpload(e, task.id)" />
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- RIGHT: Notes + Priority -->
        <aside class="hidden w-[220px] shrink-0 border-l border-[#E4E7F0] bg-white xl:flex xl:flex-col">
            <div class="overflow-y-auto px-3 py-3">
                <!-- Notes (read-only) -->
                <div class="mb-4">
                    <span class="mb-2 block text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">Notes</span>
                    <div class="flex flex-col gap-1.5">
                        <div v-for="note in activeNotes" :key="note.id" class="rounded-lg p-2.5 text-[11px] font-medium leading-snug text-[#0F1623]/70" :style="{ backgroundColor: note.color || '#FEEBC8' }">
                            {{ note.content }}
                        </div>
                        <div v-if="activeNotes.length === 0" class="py-4 text-center text-[10px] text-[#9BA3B8]">
                            No notes yet.
                        </div>
                    </div>
                </div>
                <!-- Priority Dashboard -->
                <div>
                    <span class="mb-2 block text-[11px] font-semibold uppercase tracking-wider text-[#9BA3B8]">Dashboard</span>
                    <div class="grid grid-cols-2 gap-1.5">
                        <div class="rounded-lg border border-[#E4E7F0] p-2">
                            <div class="flex items-center gap-1 text-[10px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-red-500" /> High</div>
                            <div class="text-[18px] font-semibold text-[#0F1623]">{{ priorityCounts.high }}</div>
                        </div>
                        <div class="rounded-lg border border-[#E4E7F0] p-2">
                            <div class="flex items-center gap-1 text-[10px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-amber-500" /> Medium</div>
                            <div class="text-[18px] font-semibold text-[#0F1623]">{{ priorityCounts.med }}</div>
                        </div>
                        <div class="rounded-lg border border-[#E4E7F0] p-2">
                            <div class="flex items-center gap-1 text-[10px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-yellow-500" /> Low</div>
                            <div class="text-[18px] font-semibold text-[#0F1623]">{{ priorityCounts.low }}</div>
                        </div>
                        <div class="rounded-lg border border-[#E4E7F0] p-2">
                            <div class="flex items-center gap-1 text-[10px] text-[#5A6278]"><span class="inline-block h-1.5 w-1.5 rounded-full bg-gray-400" /> Unimp.</div>
                            <div class="text-[18px] font-semibold text-[#0F1623]">{{ priorityCounts.unimportant }}</div>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center justify-between rounded-lg border border-green-100 bg-green-50 px-3 py-2">
                        <span class="text-[10px] font-medium text-green-700">Done Today</span>
                        <span class="text-lg font-bold text-green-700">{{ doneToday }}</span>
                    </div>
                </div>
            </div>
        </aside>
    </div>
</template>

    <!-- SELECT ROLES DIALOG -->
    <Dialog v-model:open="showRoleDialog">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Select Roles</DialogTitle>
                <DialogDescription>Assign predefined roles to team members.</DialogDescription>
            </DialogHeader>
            <div v-if="!activeProject?.roles?.length" class="py-4 text-center text-[12px] text-[#9BA3B8]">
                No roles defined for this project. Go to Manage tab to create roles.
            </div>
            <div v-else class="flex max-h-[60vh] flex-col gap-2 overflow-y-auto">
                <div v-for="member in activeProject?.members || []" :key="member.id" class="flex items-center gap-3 rounded-lg border border-[#E4E7F0] px-3 py-2">
                    <div class="flex-1 min-w-0">
                        <div class="text-[12px] font-medium text-[#0F1623]">{{ member.user.name }}</div>
                        <div class="text-[10px] text-[#9BA3B8] truncate">{{ member.user.email }}</div>
                    </div>
                    <select
                        class="cursor-pointer rounded border border-[#E4E7F0] bg-white px-2 py-1 text-[11px] text-[#5A6278] outline-none focus:border-[#2563EB]"
                        :value="member.role_id ?? ''"
                        @change="(e) => assignMemberRole(member.id, (e.target as HTMLSelectElement).value)"
                    >
                        <option value="">No role</option>
                        <option v-for="r in activeProject.roles" :key="r.id" :value="r.id">{{ r.name }}</option>
                    </select>
                </div>
            </div>
            <div class="flex justify-end pt-1">
                <Button type="button" variant="outline" size="sm" class="border-[#E4E7F0] text-[#5A6278]" @click="showRoleDialog = false">Close</Button>
            </div>
        </DialogContent>
    </Dialog>

    <!-- CREATE TASK MODAL -->
    <Dialog v-model:open="showCreateModal">
        <DialogContent class="sm:max-w-sm">
            <DialogHeader>
                <DialogTitle class="text-[#0F1623]">Create Task</DialogTitle>
                <DialogDescription>Fill in the task details below.</DialogDescription>
            </DialogHeader>
            <form class="flex max-h-[70vh] flex-col gap-3 overflow-y-auto overflow-x-hidden px-1" @submit.prevent @keydown.enter.prevent>
                <div>
                    <Label for="m-name" class="text-[12px] font-semibold text-[#5A6278]">Task name</Label>
                    <Input id="m-name" v-model="createForm.title" placeholder="Enter task name…" required class="border-[#E4E7F0] focus:border-[#2563EB]" />
                </div>
                <div>
                    <Label class="mb-1 block text-[12px] font-semibold text-[#5A6278]">Priority</Label>
                    <div class="flex gap-2">
                        <label v-for="p in ['high', 'med', 'low', 'unimportant']" :key="p" class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-lg border px-3 py-2 text-[13px] transition-all" :class="createForm.priority === p ? 'border-' + (p === 'high' ? 'red-500 bg-red-50 text-red-600' : p === 'med' ? 'amber-500 bg-amber-50 text-amber-600' : p === 'low' ? 'yellow-500 bg-yellow-50 text-yellow-700' : 'gray-400 bg-gray-50 text-gray-600') : 'border-[#E4E7F0] text-[#5A6278]'">
                            <input type="radio" :value="p" v-model="createForm.priority" class="sr-only" />
                            <span class="inline-block h-2 w-2 rounded-full" :class="p === 'high' ? 'bg-red-500' : p === 'med' ? 'bg-amber-500' : p === 'low' ? 'bg-yellow-500' : 'bg-gray-400'" />
                            {{ p === 'high' ? 'High' : p === 'med' ? 'Medium' : p === 'low' ? 'Low' : 'Unimp.' }}
                        </label>
                    </div>
                </div>
                <div>
                    <Label for="m-type" class="text-[12px] font-semibold text-[#5A6278]">File type</Label>
                    <select id="m-type" v-model="createForm.file_type" class="w-full cursor-pointer rounded-lg border border-[#E4E7F0] bg-transparent px-3 py-2 text-[13px] outline-none focus:border-[#2563EB]">
                        <option value="">None</option>
                        <option value="DOC">DOC</option>
                        <option value="PDF">PDF</option>
                        <option value="XLS">XLS</option>
                    </select>
                </div>
                <div>
                    <Label for="m-deadline" class="text-[12px] font-semibold text-[#5A6278]">Deadline</Label>
                    <Input id="m-deadline" v-model="createForm.deadline" type="date" :min="today" class="border-[#E4E7F0] focus:border-[#2563EB]" />
                </div>
                <div>
                    <Label class="mb-1 text-[12px] font-semibold text-[#5A6278]">To-Do</Label>
                    <div class="mb-2 flex flex-col gap-1">
                        <div v-for="(sub, i) in pendingSubs" :key="i" class="flex items-center gap-2 text-[13px]">
                            <Circle class="h-3.5 w-3.5 shrink-0 text-[#2563EB]" />
                            <span class="flex-1 text-[#0F1623]">{{ sub.title }}</span>
                            <button type="button" class="text-[#9BA3B8] hover:text-red-500" @click="removePendingSub(i)">
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                    <div class="flex gap-1.5">
                        <input v-model="subInput" placeholder="Add to-do item…" class="flex-1 rounded-lg border border-[#E4E7F0] bg-white px-3 py-1.5 text-[12px] outline-none focus:border-[#2563EB]" @keydown.enter.prevent="addPendingSub" />
                        <Button type="button" variant="outline" size="sm" class="border-[#E4E7F0] text-[#5A6278] hover:bg-[#F0F2F8]" @click="addPendingSub">+ Add</Button>
                    </div>
                </div>
                <div>
                    <Label class="mb-1 text-[12px] font-semibold text-[#5A6278]">Attachment</Label>
                    <div class="flex cursor-pointer items-center gap-2 rounded-lg border border-dashed border-[#E4E7F0] px-3 py-2.5 text-[13px] text-[#9BA3B8] transition-all hover:border-[#2563EB] hover:bg-[#EEF3FF] hover:text-[#2563EB]" @click="fileInputRef?.click()">
                        <Upload class="h-4 w-4" />
                        <span class="flex-1">{{ selectedFile ? selectedFile.name : 'Choose file…' }}</span>
                        <input ref="fileInputRef" type="file" :accept="fileAcceptAttr" class="hidden" @change="handleFileChange" />
                    </div>
                </div>
                <div>
                    <Label for="m-desc" class="text-[12px] font-semibold text-[#5A6278]">Description</Label>
                    <textarea id="m-desc" v-model="createForm.description" rows="3" placeholder="Write description…" class="w-full resize-y rounded-lg border border-[#E4E7F0] bg-transparent px-3 py-2 text-[13px] outline-none focus:border-[#2563EB]" />
                </div>
                <div class="flex gap-2 pt-1">
                    <Button type="button" variant="outline" size="sm" class="border-[#E4E7F0] text-[#5A6278] hover:bg-[#F0F2F8]" @click="showCreateModal = false">Cancel</Button>
                    <Button type="button" size="sm" class="flex-1 bg-[#2563EB] text-white hover:bg-[#1d4ed8]" @click="createTask">Save task</Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
