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
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->foreignId('department_to_id')->nullable()->constrained('edts_departments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->dropForeign(['department_to_id']);
            $table->dropColumn('department_to_id');
        });
    }
};
