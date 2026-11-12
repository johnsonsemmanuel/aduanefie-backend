<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customs_duties', function (Blueprint $table) {
            $table->id();
            $table->string('destination_country');
            $table->string('hs_code')->comment('HS code or chapter prefix, e.g. 1005, 1201');
            $table->string('hs_description');
            $table->float('duty_rate_percent')->default(0)->comment('Import duty rate in percent');
            $table->float('vat_rate_percent')->default(0)->comment('VAT rate in percent');
            $table->float('excise_rate_percent')->default(0)->comment('Excise rate in percent');
            $table->boolean('requires_sps_certificate')->default(0)->comment('Sanitary & phytosanitary certificate required');
            $table->boolean('requires_phytosanitary')->default(0)->comment('Phytosanitary certificate required');
            $table->boolean('requires_origin_certificate')->default(0)->comment('Certificate of origin required');
            $table->timestamps();

            $table->index(['destination_country', 'hs_code']);
            $table->index('destination_country');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customs_duties');
    }
};
