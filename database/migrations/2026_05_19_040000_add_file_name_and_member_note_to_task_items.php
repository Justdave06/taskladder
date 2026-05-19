<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('task_items', function (Blueprint $table) {
            $table->string('file_name')->nullable()->after('file_type');
            $table->text('member_note')->nullable()->after('completion_notes');
        });
    }

    public function down(): void
    {
        Schema::table('task_items', function (Blueprint $table) {
            $table->dropColumn(['file_name', 'member_note']);
        });
    }
};
