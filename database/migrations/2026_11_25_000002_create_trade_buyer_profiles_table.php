<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates:
     *  - trade_buyer_profiles: stores applicant details and approval state machine.
     *  - trade_buyer_audit_logs: immutable chronological record of all status transitions.
     */
    public function up(): void
    {
        if (! Schema::hasTable('trade_buyer_profiles')) {
            Schema::create('trade_buyer_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique()->index();
                $table->string('applicant_name', 120);
                $table->string('organization_name', 150)->nullable()->comment('Optional for individual traders');
                $table->string('country_of_operation', 80);
                $table->string('contact_phone', 40);
                $table->string('contact_email', 150)->nullable();
                $table->string('business_type', 80)->nullable();
                $table->json('target_commodities')->nullable();
                $table->json('intended_destinations')->nullable();
                $table->string('estimated_volume', 100)->nullable()->comment('Flexible volume indicator');
                $table->string('tax_or_reg_number', 80)->nullable()->comment('Optional corporate/trader registration');
                $table->text('sourcing_notes')->nullable();
                $table->string('status', 30)->default('pending')->index();
                $table->text('rejection_reason')->nullable();
                $table->text('revocation_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable()->index();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('trade_buyer_audit_logs')) {
            Schema::create('trade_buyer_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trade_buyer_profile_id')->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->string('action', 50); // submitted, status_changed, approved, rejected, revoked, resubmitted
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('actor_type', 30); // admin, user, system
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->foreign('trade_buyer_profile_id')
                    ->references('id')
                    ->on('trade_buyer_profiles')
                    ->onDelete('cascade');
                $table->index(['trade_buyer_profile_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trade_buyer_audit_logs');
        Schema::dropIfExists('trade_buyer_profiles');
    }
};
