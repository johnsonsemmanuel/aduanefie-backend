<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pro_customer_plans')) {
            Schema::create('pro_customer_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('tagline', 255)->nullable();
                $table->decimal('price', 10, 2);
                $table->integer('validity_days')->default(30);
                $table->string('benefit_type', 40)->default('delivery_fee');
                $table->string('offer_type', 40)->default('free');
                $table->decimal('charge_discount_percentage', 5, 2)->default(100);
                $table->integer('min_order_status')->default(1);
                $table->decimal('min_order_amount', 10, 2)->default(100);
                $table->boolean('status')->default(true);
                $table->timestamps();
            });

            DB::table('pro_customer_plans')->insert([
                [
                    'name' => 'Aduanefie Fresh Monthly',
                    'tagline' => 'Free local farm delivery on orders above GH¢100 + priority harvest alerts',
                    'price' => 29.00,
                    'validity_days' => 30,
                    'benefit_type' => 'delivery_fee',
                    'offer_type' => 'free',
                    'charge_discount_percentage' => 100.00,
                    'min_order_status' => 1,
                    'min_order_amount' => 100.00,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'name' => 'Harvest Season Pass',
                    'tagline' => '90 days of free local delivery on orders above GH¢80 + early group-buy access',
                    'price' => 75.00,
                    'validity_days' => 90,
                    'benefit_type' => 'delivery_fee',
                    'offer_type' => 'free',
                    'charge_discount_percentage' => 100.00,
                    'min_order_status' => 1,
                    'min_order_amount' => 80.00,
                    'status' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (! Schema::hasTable('pro_customer_subscriptions')) {
            Schema::create('pro_customer_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('plan_id')->index();
                $table->string('status', 30)->default('active')->index();
                $table->string('payment_method', 50)->default('wallet');
                $table->decimal('amount_paid', 10, 2)->default(0);
                $table->timestamp('start_at')->nullable();
                $table->timestamp('end_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pro_customer_subscriptions');
        Schema::dropIfExists('pro_customer_plans');
    }
};
