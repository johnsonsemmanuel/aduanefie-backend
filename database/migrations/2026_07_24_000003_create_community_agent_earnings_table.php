<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_agent_earnings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('delivery_man_id');
            $table->unsignedBigInteger('order_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('type', 50)->default('delivery');
            $table->text('note')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();

            $table->foreign('delivery_man_id')->references('id')->on('delivery_men')->cascadeOnDelete();
            $table->foreign('order_id')->references('id')->on('orders')->nullOnDelete();
            $table->index(['delivery_man_id', 'status']);
        });

        Schema::table('delivery_men', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_men', 'total_earnings')) {
                $table->decimal('total_earnings', 12, 2)->default(0)->after('loyalty_point');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_agent_earnings');

        Schema::table('delivery_men', function (Blueprint $table) {
            if (Schema::hasColumn('delivery_men', 'total_earnings')) {
                $table->dropColumn('total_earnings');
            }
        });
    }
};
