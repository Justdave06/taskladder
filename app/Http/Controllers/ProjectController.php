<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::with('creator')
            ->withCount('members');
        if (!$user->is_superadmin) {
            $projects->where('company_id', $user->company_id);
        }
        $projects = $projects->latest()->get()
            ->load(['members.taskItems' => fn ($q) => $q->select('id', 'project_member_id', 'is_completed')]);

        return Inertia::render('taskladder/Projects/Index', [
            'projects' => $projects,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $data = $validated;
        $data['company_id'] = $request->user()->company_id;
        $request->user()->createdProjects()->create($data);

        return redirect()->back();
    }

    public function update(Request $request, Project $project): RedirectResponse
    {
        Gate::allowIf(fn () => $request->user()->is_superadmin);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $project->update($validated);

        return redirect()->back();
    }

    public function show(Request $request, Project $project): Response
    {
        $user = $request->user();
        $project->load(['creator', 'members.user', 'members.taskItems']);

        $users = \App\Models\User::query();
        if (!$user->is_superadmin) {
            $users->where('company_id', $user->company_id);
        }

        return Inertia::render('taskladder/Projects/Show', [
            'project' => $project,
            'users' => $users->get(['id', 'name', 'email']),
        ]);
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::allowIf(fn () => request()->user()->is_superadmin);

        $project->delete();

        return redirect()->back();
    }
}
