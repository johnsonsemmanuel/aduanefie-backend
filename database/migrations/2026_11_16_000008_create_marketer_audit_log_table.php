<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketer_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->onDelete('cascade');
            $table->string('action'); // payout.approved, payout.rejected, payout.paid, rate.updated, store.attributed
            $table->unsignedInteger('subject_id')->nullable(); // payout id, marketer id, store id
            $table->string('subject_type')->nullable(); // payout, marketer, store
            $table->json('before')->nullable(); // previous values
            $table->json('after')->nullable(); // new values
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['admin_id', 'created_at']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_audit_logs');
    }
};
