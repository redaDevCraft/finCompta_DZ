<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('approval_status', 32)->default('none')->after('status');
            $table->foreignId('proof_uploaded_by')->nullable()->after('proof_upload_path')->constrained('users')->nullOnDelete();
            $table->string('proof_mime', 120)->nullable()->after('proof_uploaded_by');
            $table->unsignedBigInteger('proof_size_bytes')->nullable()->after('proof_mime');
            $table->string('proof_sha256', 64)->nullable()->after('proof_size_bytes');
            $table->foreignId('admin_confirmed_by')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_confirmed_at')->nullable()->after('admin_confirmed_by');
            $table->foreignId('admin_rejected_by')->nullable()->after('admin_confirmed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_rejected_at')->nullable()->after('admin_rejected_by');
            $table->index(['gateway', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'approval_status']);
            $table->dropConstrainedForeignId('proof_uploaded_by');
            $table->dropConstrainedForeignId('admin_confirmed_by');
            $table->dropConstrainedForeignId('admin_rejected_by');
            $table->dropColumn([
                'approval_status',
                'proof_mime',
                'proof_size_bytes',
                'proof_sha256',
                'admin_confirmed_at',
                'admin_rejected_at',
            ]);
        });
    }
};
