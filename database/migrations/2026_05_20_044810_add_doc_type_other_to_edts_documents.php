<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->string('doc_type_other')->nullable()->after('forwarded_to_id');
        });
    }

    public function down(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->dropColumn('doc_type_other');
        });
    }
};
