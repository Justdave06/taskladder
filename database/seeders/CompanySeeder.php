<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $superadmin = User::where('is_superadmin', true)->first();
        if (!$superadmin) return;

        $company = Company::create([
            'name' => 'STEP APP',
            'created_by' => $superadmin->id,
        ]);

        User::whereNull('company_id')->update([
            'company_id' => $company->id,
        ]);
    }
}
