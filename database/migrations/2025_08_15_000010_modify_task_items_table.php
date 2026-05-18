<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_items', function (Blueprint $table) {
            $table->dropForeign(['project_member_id']);
            $table->foreignId('project_member_id')->nullable()->change();
            $table->foreign('project_member_id')->references('id')->on('project_member')->nullOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('priority')->default('med');
            $table->string('file_type')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->dropColumn('is_completed');
        });
    }

    public function down(): void
    {
        Schema::table('task_items', function (Blueprint $table) {
            $table->dropForeign(['project_member_id']);
            $table->foreignId('project_member_id')->change();
            $table->foreign('project_member_id')->references('id')->on('project_member')->cascadeOnDelete();
            $table->dropConstrainedForeignId('project_id');
            $table->dropColumn('priority');
            $table->dropColumn('file_type');
            $table->dropConstrainedForeignId('created_by');
            $table->boolean('is_completed')->default(false);
        });
    }
};
