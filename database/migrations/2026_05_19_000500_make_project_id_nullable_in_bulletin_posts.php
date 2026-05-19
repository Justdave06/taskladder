<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('bulletin_posts_tmp', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('project_id')->nullable();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('content');
                $table->string('image')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO bulletin_posts_tmp (id, project_id, user_id, content, image, created_at, updated_at) SELECT id, project_id, user_id, content, image, created_at, updated_at FROM bulletin_posts');

            Schema::drop('bulletin_posts');
            Schema::rename('bulletin_posts_tmp', 'bulletin_posts');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('bulletin_posts', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
                $table->unsignedBigInteger('project_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');

            Schema::create('bulletin_posts_tmp', function (Blueprint $table) {
                $table->id();
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->text('content');
                $table->string('image')->nullable();
                $table->timestamps();
            });

            DB::statement('INSERT INTO bulletin_posts_tmp (id, project_id, user_id, content, image, created_at, updated_at) SELECT id, project_id, user_id, content, image, created_at, updated_at FROM bulletin_posts');

            Schema::drop('bulletin_posts');
            Schema::rename('bulletin_posts_tmp', 'bulletin_posts');

            DB::statement('PRAGMA foreign_keys = ON');
        } else {
            Schema::table('bulletin_posts', function (Blueprint $table) {
                $table->foreignId('project_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('project_id')->nullable(false)->change();
            });
        }
    }
};
