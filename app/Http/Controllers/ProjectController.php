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
    public function index(): Response
    {
        $projects = Project::with('creator')
            ->withCount('members')
            ->latest()
            ->get()
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

        $request->user()->createdProjects()->create($validated);

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

    public function show(Project $project): Response
    {
        $project->load(['creator', 'members.user', 'members.taskItems']);

        return Inertia::render('taskladder/Projects/Show', [
            'project' => $project,
            'users' => \App\Models\User::all(['id', 'name', 'email']),
        ]);
    }

    public function destroy(Project $project): RedirectResponse
    {
        Gate::allowIf(fn () => request()->user()->is_superadmin);

        $project->delete();

        return redirect()->back();
    }
}
