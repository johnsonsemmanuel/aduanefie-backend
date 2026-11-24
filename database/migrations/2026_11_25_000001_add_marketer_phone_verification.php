<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track mobile-money name verification for marketer applications.
 *
 * The verified name and number are carried into the application, and therefore
 * into the admin account on approval, so the number used for payout is the one
 * the applicant actually proved they own. Manual entry is only permitted after
 * MAX_ATTEMPTS failed verifications, and that rule is enforced on the server.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketer_phone_verifications')) {
            Schema::create('marketer_phone_verifications', function (Blueprint $table) {
                $table->id();
                $table->string('phone', 30)->unique();
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->string('verified_name', 150)->nullable();
                $table->string('network', 50)->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamps();
            });
        }

        if (Schema::hasTable('marketer_applications')) {
            Schema::table('marketer_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('marketer_applications', 'phone_verified')) {
                    $table->boolean('phone_verified')->default(false)->after('phone');
                }
                if (! Schema::hasColumn('marketer_applications', 'phone_verification_id')) {
                    $table->unsignedBigInteger('phone_verification_id')->nullable()->after('phone_verified')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketer_applications')) {
            // SQLite cannot drop two columns in a single blueprint pass.
            foreach (['phone_verification_id', 'phone_verified'] as $column) {
                if (Schema::hasColumn('marketer_applications', $column)) {
                    Schema::table('marketer_applications', function (Blueprint $table) use ($column) {
                        $table->dropColumn($column);
                    });
                }
            }
        }

        Schema::dropIfExists('marketer_phone_verifications');
    }
};
