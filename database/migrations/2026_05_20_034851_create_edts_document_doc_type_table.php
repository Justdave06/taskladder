<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edts_document_doc_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('edts_document_id')->constrained('edts_documents')->cascadeOnDelete();
            $table->foreignId('edts_doc_type_id')->constrained('edts_doc_types')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['edts_document_id', 'edts_doc_type_id'], 'edts_doc_dt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edts_document_doc_type');
    }
};
