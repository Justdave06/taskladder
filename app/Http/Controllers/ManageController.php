<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ManageController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $projects = Project::where('created_by', $user->id)
            ->with(['members' => fn ($q) => $q->with('user'), 'creator', 'roles'])
            ->latest()
            ->get();

        $allUsers = User::where('id', '!=', $user->id)
            ->get(['id', 'name', 'email']);

        return Inertia::render('taskladder/Manage/Index', [
            'projects' => $projects,
            'allUsers' => $allUsers,
        ]);
    }

    public function storeProject(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        $request->user()->createdProjects()->create($validated);

        return redirect()->back();
    }

    public function destroyProject(Project $project): RedirectResponse
    {
        if ($project->created_by !== request()->user()->id) {
            abort(403);
        }

        $project->members()->delete();
        $project->delete();

        return redirect()->back();
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        return redirect()->back();
    }

    public function updateMember(Request $request, ProjectMember $member): RedirectResponse
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'max:255'],
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $member->update($validated);

        return redirect()->back();
    }

    public function storeRole(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $project = Project::findOrFail($validated['project_id']);

        if ($project->created_by !== $request->user()->id) {
            abort(403);
        }

        $project->roles()->create($validated);

        return redirect()->back();
    }

    public function destroyRole(Role $role): RedirectResponse
    {
        if ($role->project->created_by !== request()->user()->id) {
            abort(403);
        }

        $role->delete();

        return redirect()->back();
    }

    public function assignRole(Request $request, ProjectMember $member): RedirectResponse
    {
        $validated = $request->validate([
            'role_id' => ['nullable', 'exists:roles,id'],
        ]);

        $member->update($validated);

        return redirect()->back();
    }

    public function removeMember(ProjectMember $member): RedirectResponse
    {
        if ($member->project->created_by !== request()->user()->id) {
            abort(403);
        }

        $member->delete();

        return redirect()->back();
    }
}
