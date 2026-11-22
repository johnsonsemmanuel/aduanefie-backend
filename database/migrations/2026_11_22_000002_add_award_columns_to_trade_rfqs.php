<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('trade_rfqs')) {
            Schema::table('trade_rfqs', function (Blueprint $table) {
                if (! Schema::hasColumn('trade_rfqs', 'accepted_quote_id')) {
                    $table->unsignedBigInteger('accepted_quote_id')->nullable()->after('status');
                }
                if (! Schema::hasColumn('trade_rfqs', 'awarded_at')) {
                    $table->timestamp('awarded_at')->nullable()->after('accepted_quote_id');
                }
            });
        }

        if (Schema::hasTable('trade_rfq_quotes')) {
            Schema::table('trade_rfq_quotes', function (Blueprint $table) {
                if (! Schema::hasColumn('trade_rfq_quotes', 'status')) {
                    $table->string('status', 30)->default('pending')->after('notes');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('trade_rfqs')) {
            Schema::table('trade_rfqs', function (Blueprint $table) {
                foreach (['awarded_at', 'accepted_quote_id'] as $col) {
                    if (Schema::hasColumn('trade_rfqs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
        if (Schema::hasTable('trade_rfq_quotes')) {
            Schema::table('trade_rfq_quotes', function (Blueprint $table) {
                if (Schema::hasColumn('trade_rfq_quotes', 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
