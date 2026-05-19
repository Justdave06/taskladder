<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('connect_code', 8)->unique()->nullable()->after('color');
        });

        foreach (\App\Models\Company::all() as $company) {
            $company->connect_code = strtoupper(Str::random(8));
            $company->saveQuietly();
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('connect_code');
        });
    }
};
