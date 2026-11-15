<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('trade_rfqs')) {
            Schema::create('trade_rfqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('buyer_name', 120);
                $table->string('buyer_company', 150)->nullable();
                $table->string('buyer_phone', 40);
                $table->string('buyer_email', 150)->nullable();
                $table->string('commodity', 150);
                $table->string('category', 100)->default('Grains & Cereals');
                $table->decimal('quantity', 12, 2);
                $table->string('unit', 60)->default('100 kg bag');
                $table->decimal('target_price_ghs', 12, 2)->nullable();
                $table->string('incoterm', 10)->default('DDP');
                $table->string('origin_country', 80)->default('Ghana');
                $table->string('destination_country', 80)->default('Ghana');
                $table->string('destination_city', 120)->nullable();
                $table->string('trade_bloc', 30)->default('ECOWAS');
                $table->boolean('requires_insurance')->default(true);
                $table->boolean('requires_phytosanitary')->default(true);
                $table->date('deadline_date')->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('open')->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('trade_rfq_quotes')) {
            Schema::create('trade_rfq_quotes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trade_rfq_id')->index();
                $table->unsignedBigInteger('store_id')->nullable()->index();
                $table->string('supplier_name', 150);
                $table->string('supplier_phone', 40)->nullable();
                $table->decimal('quoted_unit_price_ghs', 12, 2);
                $table->string('incoterm', 10)->default('DDP');
                $table->unsignedInteger('lead_time_days')->default(7);
                $table->string('compliance_docs', 255)->nullable();
                $table->text('notes')->nullable();
                $table->string('status', 30)->default('submitted');
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trade_rfq_quotes');
        Schema::dropIfExists('trade_rfqs');
    }
};
