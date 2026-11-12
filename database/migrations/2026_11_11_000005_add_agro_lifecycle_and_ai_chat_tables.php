<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stores')) {
            Schema::table('stores', function (Blueprint $table) {
                if (! Schema::hasColumn('stores', 'farm_type')) {
                    $table->string('farm_type', 40)->default('commercial');
                }
                if (! Schema::hasColumn('stores', 'is_backyard')) {
                    $table->boolean('is_backyard')->default(false);
                }
                if (! Schema::hasColumn('stores', 'country')) {
                    $table->string('country', 100)->nullable()->default('Ghana');
                }
                if (! Schema::hasColumn('stores', 'region')) {
                    $table->string('region', 120)->nullable();
                }
                if (! Schema::hasColumn('stores', 'farm_size_acres')) {
                    $table->decimal('farm_size_acres', 10, 2)->nullable();
                }
                if (! Schema::hasColumn('stores', 'primary_crops')) {
                    $table->text('primary_crops')->nullable();
                }
                if (! Schema::hasColumn('stores', 'cultivation_status')) {
                    $table->string('cultivation_status', 30)->default('active');
                }
                if (! Schema::hasColumn('stores', 'next_harvest_date')) {
                    $table->date('next_harvest_date')->nullable();
                }
                if (! Schema::hasColumn('stores', 'marketer_verified')) {
                    $table->boolean('marketer_verified')->default(false);
                }
                if (! Schema::hasColumn('stores', 'marketer_verified_at')) {
                    $table->timestamp('marketer_verified_at')->nullable();
                }
                if (! Schema::hasColumn('stores', 'marketer_verification_notes')) {
                    $table->text('marketer_verification_notes')->nullable();
                }
            });
        }

        if (Schema::hasTable('items')) {
            Schema::table('items', function (Blueprint $table) {
                $cols = [
                    'origin_farm' => fn (Blueprint $t) => $t->string('origin_farm')->nullable(),
                    'origin_country' => fn (Blueprint $t) => $t->string('origin_country', 100)->nullable()->default('Ghana'),
                    'seasonality' => fn (Blueprint $t) => $t->string('seasonality', 100)->nullable(),
                    'certification' => fn (Blueprint $t) => $t->string('certification')->nullable(),
                    'cold_chain_required' => fn (Blueprint $t) => $t->boolean('cold_chain_required')->default(false),
                    'harvest_date' => fn (Blueprint $t) => $t->date('harvest_date')->nullable(),
                    'expiry_date' => fn (Blueprint $t) => $t->date('expiry_date')->nullable(),
                    'perishability_days' => fn (Blueprint $t) => $t->integer('perishability_days')->nullable(),
                    'minimum_order_qty' => fn (Blueprint $t) => $t->integer('minimum_order_qty')->default(1),
                    'farming_status' => fn (Blueprint $t) => $t->string('farming_status', 30)->default('in_stock'),
                    'planting_date' => fn (Blueprint $t) => $t->date('planting_date')->nullable(),
                    'expected_harvest_date' => fn (Blueprint $t) => $t->date('expected_harvest_date')->nullable(),
                    'crop_cycle_days' => fn (Blueprint $t) => $t->integer('crop_cycle_days')->nullable(),
                    'pre_order_enabled' => fn (Blueprint $t) => $t->boolean('pre_order_enabled')->default(false),
                    'pre_order_limit' => fn (Blueprint $t) => $t->integer('pre_order_limit')->nullable(),
                    'is_backyard' => fn (Blueprint $t) => $t->boolean('is_backyard')->default(false),
                    'marketer_verified' => fn (Blueprint $t) => $t->boolean('marketer_verified')->default(false),
                    'marketer_verified_at' => fn (Blueprint $t) => $t->timestamp('marketer_verified_at')->nullable(),
                ];

                foreach ($cols as $name => $def) {
                    if (! Schema::hasColumn('items', $name)) {
                        $def($table);
                    }
                }
            });
        }

        if (Schema::hasTable('admins') && ! Schema::hasColumn('admins', 'marketer_code')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('marketer_code', 32)->nullable();
            });
        }

        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table) {
                if (! Schema::hasColumn('orders', 'is_pre_order')) {
                    $table->boolean('is_pre_order')->default(false);
                }
                if (! Schema::hasColumn('orders', 'expected_harvest_date')) {
                    $table->date('expected_harvest_date')->nullable();
                }
            });
        }

        if (! Schema::hasTable('ai_conversations')) {
            Schema::create('ai_conversations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('guest_id', 100)->nullable()->index();
                $table->unsignedBigInteger('module_id')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->string('title')->nullable();
                $table->string('status', 30)->default('active');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_messages')) {
            Schema::create('ai_messages', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('conversation_id')->index();
                $table->string('role', 20);
                $table->longText('content')->nullable();
                $table->string('tool_name', 100)->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
