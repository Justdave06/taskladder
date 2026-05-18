<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        DB::statement('INSERT INTO messages_tmp (id, user_id, project_id, content, created_at, updated_at)
                        SELECT id, user_id, project_id, content, created_at, updated_at FROM messages');

        Schema::drop('messages');
        Schema::rename('messages_tmp', 'messages');
    }

    public function down(): void
    {
        Schema::create('messages_tmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->timestamps();
        });

        DB::statement('INSERT INTO messages_tmp (id, user_id, project_id, content, created_at, updated_at)
                        SELECT id, user_id, project_id, content, created_at, updated_at FROM messages');

        Schema::drop('messages');
        Schema::rename('messages_tmp', 'messages');
    }
};
