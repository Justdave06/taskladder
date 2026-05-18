<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\TaskChecklistItem;
use App\Models\TaskItem;
use App\Models\Message;
use App\Models\Note;
use App\Notifications\TaskAssigned;
use App\Notifications\TaskCompleted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskItemController extends Controller
{
    public function board(Request $request): Response
    {
        $user = $request->user();

        $user->last_active_at = now();
        $user->saveQuietly();

        $projectIds = Project::where('created_by', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $projects = Project::whereIn('id', $projectIds)
            ->with(['members' => function ($q) {
                $q->whereIn('status', ['accepted', 'completed']);
            }, 'members.user', 'roles'])
            ->get();

        foreach ($projects as $project) {
            $isCreator = $project->created_by === $user->id;
            foreach ($project->members as $member) {
                $query = $member->taskItems()->with(['checklistItems', 'projectMember.user']);
                if (!$isCreator) {
                    $query->whereIn('status', ['accepted', 'completed']);
                }
                $member->setRelation('task_items', $query->latest()->get());
            }
        }

        $unassignedTasks = TaskItem::whereNull('project_member_id')
            ->whereIn('project_id', $projectIds)
            ->with(['checklistItems', 'creator'])
            ->latest()
            ->get();

        return Inertia::render('taskladder/Tasks/Index', [
            'projects' => $projects,
            'unassignedTasks' => $unassignedTasks,
            'notes' => Note::whereIn('project_id', $projectIds)
                ->orderBy('position')
                ->get()
                ->groupBy('project_id'),
            'messages' => Message::whereIn('project_id', $projectIds)
                ->with('user')
                ->latest()
                ->get()
                ->groupBy('project_id'),
        ]);
    }

    public function show(Request $request, TaskItem $task): Response
    {
        $task->load(['checklistItems', 'project', 'projectMember.user', 'creator']);

        return Inertia::render('taskladder/Tasks/Show', [
            'task' => $task,
        ]);
    }

    public function list(Request $request): Response
    {
        $user = $request->user();

        $projectIds = Project::where('created_by', $user->id)
            ->orWhereHas('members', fn ($q) => $q->where('user_id', $user->id))
            ->pluck('id');

        $tasks = TaskItem::whereIn('project_id', $projectIds)
            ->with(['checklistItems', 'project', 'projectMember.user'])
            ->latest()
            ->get();

        $projects = Project::whereIn('id', $projectIds)->get(['id', 'title']);

        return Inertia::render('taskladder/Tasks/List', [
            'tasks' => $tasks,
            'projects' => $projects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'priority' => ['required', 'in:high,med,low,unimportant'],
            'file_type' => ['nullable', 'string', 'max:10'],
            'project_id' => ['required', 'exists:projects,id'],
            'checklist' => ['nullable', 'array'],
            'checklist.*.title' => ['required', 'string', 'max:255'],
            'file' => ['nullable', 'file', 'max:10240'],
            'deadline' => ['nullable', 'date'],
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('task-files', 'public');
        }

        $task = TaskItem::create([
            'project_id' => $validated['project_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'deadline' => $validated['deadline'] ?? null,
            'priority' => $validated['priority'],
            'file_type' => !empty($validated['file_type']) ? $validated['file_type'] : null,
            'file_path' => $filePath,
            'created_by' => $request->user()->id,
        ]);

        if (!empty($validated['checklist'])) {
            foreach ($validated['checklist'] as $item) {
                $task->checklistItems()->create([
                    'title' => $item['title'],
                ]);
            }
        }

        return redirect()->back();
    }

    public function assign(Request $request, TaskItem $task): RedirectResponse
    {
        $validated = $request->validate([
            'project_member_id' => ['required', 'exists:project_member,id'],
        ]);

        $member = ProjectMember::findOrFail($validated['project_member_id']);

        $task->update([
            'project_member_id' => $member->id,
            'status' => 'pending',
        ]);

        $member->user->notify(new TaskAssigned($task, $request->user()->name));

        return redirect()->back();
    }

    public function accept(Request $request, TaskItem $task): RedirectResponse
    {
        if ($task->project_member_id) {
            $member = $task->projectMember;
            if ($member && $member->user_id === $request->user()->id) {
                $task->update(['status' => 'accepted']);
            }
        }

        return redirect()->route('board');
    }

    public function decline(Request $request, TaskItem $task): RedirectResponse
    {
        if ($task->project_member_id) {
            $member = $task->projectMember;
            if ($member && $member->user_id === $request->user()->id) {
                $task->update(['status' => 'declined', 'project_member_id' => null]);
            }
        }

        return redirect()->route('board');
    }

    public function done(Request $request, TaskItem $task): RedirectResponse
    {
        $member = $task->projectMember;

        abort_unless($member && $member->user_id === $request->user()->id, 403);
        abort_unless($task->status === 'accepted', 422);

        if ($task->checklistItems()->where('is_completed', false)->exists()) {
            return redirect()->back()->withErrors(['All checklist items must be completed.']);
        }

        $validated = $request->validate([
            'completion_notes' => ['nullable', 'string', 'max:50000'],
        ]);

        $task->update([
            'status' => 'completed',
            'completion_notes' => $validated['completion_notes'] ?? null,
        ]);

        $boss = $task->project?->creator;
        if ($boss) {
            $boss->notify(new TaskCompleted($task, $request->user()->name));
        }

        return redirect()->back();
    }

    public function unassign(Request $request, TaskItem $task): RedirectResponse
    {
        $task->update([
            'project_member_id' => null,
            'status' => 'pending',
        ]);

        return redirect()->back();
    }

    public function toggleChecklist(Request $request, TaskChecklistItem $item): RedirectResponse
    {
        $validated = $request->validate([
            'is_completed' => ['required', 'boolean'],
        ]);

        $item->update($validated);

        return redirect()->back();
    }

    public function update(Request $request, TaskItem $task): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:50000'],
            'priority' => ['sometimes', 'required', 'in:high,med,low,unimportant'],
            'file_type' => ['nullable', 'string', 'max:10'],
            'deadline' => ['nullable', 'date'],
            'file' => ['nullable', 'file', 'max:10240'],
        ]);

        $data = collect($validated)->except('file')->toArray();

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('task-files', 'public');
            $data['file_type'] = $data['file_type'] ?? $this->inferFileType($request->file('file'));
        }

        $task->update($data);

        return redirect()->back();
    }

    private function inferFileType($file): string
    {
        $name = strtolower($file->getClientOriginalName());
        if (str_ends_with($name, '.doc') || str_ends_with($name, '.docx')) return 'DOC';
        if (str_ends_with($name, '.pdf')) return 'PDF';
        if (str_ends_with($name, '.xls') || str_ends_with($name, '.xlsx')) return 'XLS';
        return 'DOC';
    }

    public function destroy(Request $request, TaskItem $task): RedirectResponse
    {
        $task->delete();

        return redirect()->back();
    }

    public function togglePin(Request $request, TaskItem $task): RedirectResponse
    {
        $task->update(['pinned' => !$task->pinned]);

        return redirect()->back();
    }
}
