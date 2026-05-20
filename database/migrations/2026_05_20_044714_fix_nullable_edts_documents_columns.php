<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->string('type')->nullable()->change();
            $table->string('recipient_office')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
            $table->string('recipient_office')->nullable(false)->change();
        });
    }
};
