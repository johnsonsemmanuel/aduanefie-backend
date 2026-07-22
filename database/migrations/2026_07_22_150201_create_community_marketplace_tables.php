<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_zones', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zone_id')->nullable();
            $table->string('name');
            $table->string('region')->nullable();
            $table->text('description')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            $table->foreign('zone_id')->references('id')->on('zones')->nullOnDelete();
        });

        Schema::create('farm_updates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->date('update_date')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            $table->foreign('store_id')->references('id')->on('stores')->cascadeOnDelete();
        });

        Schema::create('recipes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('category')->nullable();
            $table->integer('prep_time')->nullable();
            $table->integer('cook_time')->nullable();
            $table->integer('servings')->nullable();
            $table->string('difficulty')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('recipe_ingredients', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('recipe_id');
            $table->string('name');
            $table->string('quantity');
            $table->string('unit');
            $table->timestamps();

            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
        });

        Schema::create('saved_recipes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('recipe_id');
            $table->timestamps();

            $table->unique(['user_id', 'recipe_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('recipe_id')->references('id')->on('recipes')->cascadeOnDelete();
        });

        Schema::create('marketers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->unique();
            $table->string('nid_number')->nullable();
            $table->string('nid_image')->nullable();
            $table->string('referral_code')->unique()->nullable();
            $table->decimal('total_earnings', 15, 2)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::create('marketer_referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketer_id');
            $table->unsignedBigInteger('referred_user_id')->nullable();
            $table->string('referred_name')->nullable();
            $table->string('referred_phone')->nullable();
            $table->string('referred_email')->nullable();
            $table->integer('status')->default(0);
            $table->timestamp('earned_at')->nullable();
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
            $table->foreign('referred_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::create('marketer_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('marketer_id');
            $table->decimal('amount', 15, 2);
            $table->string('type')->nullable();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->text('note')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();

            $table->foreign('marketer_id')->references('id')->on('marketers')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketer_earnings');
        Schema::dropIfExists('marketer_referrals');
        Schema::dropIfExists('marketers');
        Schema::dropIfExists('saved_recipes');
        Schema::dropIfExists('recipe_ingredients');
        Schema::dropIfExists('recipes');
        Schema::dropIfExists('farm_updates');
        Schema::dropIfExists('community_zones');
    }
};
