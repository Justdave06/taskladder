<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Users', [
            'users' => User::with('company')->get(),
            'companies' => Company::all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')],
            'password' => ['required', 'string', 'min:8'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'is_company_admin' => ['boolean'],
            'is_superadmin' => ['boolean'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'company_id' => $validated['company_id'] ?? null,
            'is_company_admin' => $validated['is_company_admin'] ?? false,
            'is_superadmin' => $validated['is_superadmin'] ?? false,
        ]);

        return redirect()->back();
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id && !$request->boolean('is_superadmin')) {
            abort(403, 'You cannot remove your own superadmin status.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'company_id' => ['nullable', 'exists:companies,id'],
            'is_company_admin' => ['boolean'],
            'is_superadmin' => ['boolean'],
        ]);

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'company_id' => $validated['company_id'] ?? null,
            'is_company_admin' => $validated['is_company_admin'] ?? false,
            'is_superadmin' => $validated['is_superadmin'] ?? false,
        ];

        if (!empty($validated['password'])) {
            $data['password'] = Hash::make($validated['password']);
        }

        $user->update($data);

        return redirect()->back();
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            abort(403, 'You cannot delete your own account.');
        }

        $user->delete();

        return redirect()->back();
    }
}
