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
        Schema::table('task_items', function (Blueprint $table) {
            $table->text('completion_notes')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('task_items', function (Blueprint $table) {
            $table->dropColumn('completion_notes');
        });
    }
};
