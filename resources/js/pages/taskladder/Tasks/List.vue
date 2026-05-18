<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { FileText, File, FileSpreadsheet } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
interface ChecklistItem {
    id: number;
    title: string;
    is_completed: boolean;
}

interface TaskData {
    id: number;
    title: string;
    priority: string;
    file_type: string | null;
    project_member_id: number | null;
    project: { id: number; title: string };
    project_member?: { user: { name: string } };
    checklist_items: ChecklistItem[];
    created_at: string;
}

defineProps<{
    tasks: TaskData[];
    projects: { id: number; title: string }[];
}>();

const priorityColors: Record<string, string> = {
    high: 'bg-red-500',
    med: 'bg-amber-500',
    low: 'bg-green-500',
};

const priorityLabels: Record<string, string> = {
    high: 'High',
    med: 'Medium',
    low: 'Low',
};

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

function taskProgress(task: TaskData): number {
    const items = task.checklist_items ?? [];

    if (items.length === 0) {
return 0;
}

    const done = items.filter((i) => i.is_completed).length;

    return Math.round((done / items.length) * 100);
}
</script>

<template>
    <Head title="Tasking" />

    <div class="p-4">
        <h2 class="mb-4 text-lg font-semibold">All Tasks</h2>

        <div v-if="tasks.length === 0" class="py-12 text-center text-sm text-muted-foreground">
            No tasks yet. Go to the Main board to create tasks.
        </div>

        <div v-else class="grid gap-3 md:grid-cols-2 lg:grid-cols-3">
            <Card v-for="task in tasks" :key="task.id">
                <CardHeader class="pb-2">
                    <div class="flex items-start justify-between gap-2">
                        <CardTitle class="text-sm">{{ task.title }}</CardTitle>
                        <span class="inline-block h-2 w-2 shrink-0 rounded-full" :class="priorityColors[task.priority]" />
                    </div>
                </CardHeader>
                <CardContent>
                    <div class="flex items-center gap-2 text-xs text-muted-foreground">
                        <Badge variant="outline" class="text-[10px]">{{ task.project.title }}</Badge>
                        <span>{{ priorityLabels[task.priority] }}</span>
                        <span v-if="task.file_type" class="flex items-center gap-1">
                            <component :is="fileIcon(task.file_type)" class="h-3 w-3" />
                            {{ task.file_type }}
                        </span>
                    </div>
                    <div v-if="task.project_member" class="mt-2 text-xs text-muted-foreground">
                        Assigned to: {{ task.project_member.user.name }}
                    </div>
                    <div v-else class="mt-2 text-xs text-muted-foreground">Unassigned</div>
                    <div v-if="task.checklist_items?.length" class="mt-2">
                        <div class="mb-1 flex h-1.5 overflow-hidden rounded-full bg-muted">
                            <div
                                class="h-full rounded-full bg-blue-600 transition-all"
                                :style="{ width: taskProgress(task) + '%' }"
                            />
                        </div>
                        <span class="text-[10px] text-muted-foreground">{{ taskProgress(task) }}%</span>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
