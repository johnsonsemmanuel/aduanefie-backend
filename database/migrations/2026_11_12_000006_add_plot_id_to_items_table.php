<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (! Schema::hasColumn('items', 'plot_id')) {
                $table->unsignedBigInteger('plot_id')->nullable()->index();
                $table->foreign('plot_id')->references('id')->on('farm_plots')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            if (Schema::hasColumn('items', 'plot_id')) {
                $table->dropForeign(['plot_id']);
                $table->dropColumn('plot_id');
            }
        });
    }
};
