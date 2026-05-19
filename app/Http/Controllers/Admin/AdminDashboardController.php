<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('admin/Dashboard', [
            'totalUsers' => User::count(),
            'superadminCount' => User::where('is_superadmin', true)->count(),
            'companyAdminCount' => User::where('is_company_admin', true)->count(),
            'regularUserCount' => User::where('is_superadmin', false)->where('is_company_admin', false)->count(),
            'projectCount' => Project::count(),
            'companyCount' => Company::count(),
        ]);
    }
}
