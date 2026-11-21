<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('marketer_applications') && ! Schema::hasColumn('marketer_applications', 'country_code')) {
            Schema::table('marketer_applications', function (Blueprint $table) {
                $table->char('country_code', 2)->nullable()->after('phone')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('marketer_applications') && Schema::hasColumn('marketer_applications', 'country_code')) {
            Schema::table('marketer_applications', function (Blueprint $table) {
                $table->dropColumn('country_code');
            });
        }
    }
};
