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
            $table->timestamp('reviewed_at')->nullable()->after('received_at');
            $table->timestamp('ready_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->dropColumn(['reviewed_at', 'ready_at']);
        });
    }
};
