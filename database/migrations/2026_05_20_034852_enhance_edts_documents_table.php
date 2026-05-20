<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->string('reference_number')->nullable()->unique()->after('id');
            $table->foreignId('department_from_id')->nullable()->constrained('edts_departments')->after('user_id');
            $table->string('requester_name')->nullable()->after('department_from_id');
            $table->string('requester_email')->nullable()->after('requester_name');
            $table->string('requester_phone')->nullable()->after('requester_email');
            $table->text('purpose')->nullable()->after('notes');
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->after('purpose');
            $table->foreignId('forwarded_to_id')->nullable()->constrained('users')->after('assigned_to_id');
            $table->timestamp('received_at')->nullable()->after('forwarded_to_id');
            $table->timestamp('completed_at')->nullable()->after('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('edts_documents', function (Blueprint $table) {
            $table->dropColumn([
                'reference_number',
                'department_from_id',
                'requester_name',
                'requester_email',
                'requester_phone',
                'purpose',
                'assigned_to_id',
                'forwarded_to_id',
                'received_at',
                'completed_at',
            ]);
        });
    }
};
