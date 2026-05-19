<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRoleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Roles', [
            'roles' => Role::all(['id', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        Role::create($validated);

        return redirect()->back();
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $role->update($validated);

        return redirect()->back();
    }

    public function destroy(Role $role): RedirectResponse
    {
        $role->delete();

        return redirect()->back();
    }
}
