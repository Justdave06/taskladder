<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('bulletin_posts', function (Blueprint $table) {
            $table->string('visibility', 20)->default('company')->after('company_id');
        });
    }

    public function down(): void
    {
        Schema::table('bulletin_posts', function (Blueprint $table) {
            $table->dropColumn('visibility');
        });
    }
};
