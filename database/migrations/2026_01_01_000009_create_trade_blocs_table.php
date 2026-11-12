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
        Schema::create('trade_blocs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('e.g. ECOWAS, SADC, EAC, COMESA');
            $table->string('slug')->unique()->comment('ecowas, sadc, eac, comesa');
            $table->string('description')->nullable();
            $table->text('member_countries')->comment('JSON array of member country names');
            $table->string('primary_currencies')->comment('JSON array of currency codes used in bloc');
            $table->string('common_languages')->comment('e.g. English, French, Portuguese');
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->index('slug');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('trade_blocs');
    }
};
