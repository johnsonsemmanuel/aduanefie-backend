<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_product_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('guest_id')->nullable();
            $table->string('phone')->index();
            $table->unsignedBigInteger('product_id')->index();
            $table->string('product_type')->default('Item');
            $table->boolean('subscribed')->default(true);
            $table->timestamp('subscribed_at')->useCurrent();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'product_type']);
            $table->index(['phone', 'subscribed']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_product_subscriptions');
    }
};
