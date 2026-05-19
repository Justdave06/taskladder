<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('to_company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->timestamps();
            $table->unique(['from_company_id', 'to_company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_connections');
    }
};
