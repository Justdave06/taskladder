<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Companies', [
            'companies' => Company::with(['creator', 'users' => fn ($q) => $q->where('is_company_admin', true)])->get(),
            'users' => User::where('is_superadmin', false)->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:2048'],
        ]);

        $data = [
            'name' => $validated['name'],
            'created_by' => $request->user()->id,
            'color' => $this->generateColor($validated['name']),
        ];

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        Company::create($data);

        return redirect()->back();
    }

    private function generateColor(string $name): string
    {
        $hash = crc32($name);
        $hue = $hash % 360;
        return sprintf('#%02x%02x%02x', ...$this->hslToRgb($hue, 55, 45));
    }

    private function hslToRgb(int $h, int $s, int $l): array
    {
        $s /= 100;
        $l /= 100;
        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        $r = $g = $b = 0;
        if ($h < 60) { $r = $c; $g = $x; }
        elseif ($h < 120) { $r = $x; $g = $c; }
        elseif ($h < 180) { $g = $c; $b = $x; }
        elseif ($h < 240) { $g = $x; $b = $c; }
        elseif ($h < 300) { $r = $x; $b = $c; }
        else { $r = $c; $b = $x; }

        return [(int)(($r + $m) * 255), (int)(($g + $m) * 255), (int)(($b + $m) * 255)];
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $company->update($validated);

        return redirect()->back();
    }

    public function destroy(Company $company): RedirectResponse
    {
        if ($company->users()->exists()) {
            return redirect()->back()->withErrors(['company' => 'Cannot delete a company that has users assigned.']);
        }

        $company->delete();

        return redirect()->back();
    }

    public function assignAdmin(Request $request, Company $company): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $user = User::findOrFail($validated['user_id']);

        User::where('company_id', $company->id)
            ->where('is_company_admin', true)
            ->update(['is_company_admin' => false]);

        $user->update([
            'company_id' => $company->id,
            'is_company_admin' => true,
        ]);

        return redirect()->back();
    }
}
