<?php

namespace Tests\Feature;

use App\Models\Item;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class GroupBuyEligibilityTest extends TestCase
{
    public function test_bulk_discovery_accepts_moq_or_nonempty_tiers_and_rejects_empty_json(): void
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->integer('moq')->default(1);
            $table->json('bulk_pricing_tiers')->nullable();
        });
        Schema::create('bulk_pricing_tiers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('item_id');
            $table->string('status');
            $table->integer('min_quantity')->default(1);
        });
        DB::table('items')->insert([
            ['id' => 1, 'moq' => 2, 'bulk_pricing_tiers' => null],
            ['id' => 2, 'moq' => 1, 'bulk_pricing_tiers' => '[{"min_quantity":5}]'],
            ['id' => 3, 'moq' => 1, 'bulk_pricing_tiers' => '[]'],
            ['id' => 4, 'moq' => 1, 'bulk_pricing_tiers' => 'null'],
            ['id' => 5, 'moq' => 1, 'bulk_pricing_tiers' => null],
            ['id' => 6, 'moq' => 1, 'bulk_pricing_tiers' => null],
        ]);
        DB::table('bulk_pricing_tiers')->insert([
            ['item_id' => 5, 'status' => 'active', 'min_quantity' => 5],
            ['item_id' => 6, 'status' => 'inactive', 'min_quantity' => 5],
        ]);
        $this->assertSame([1, 2, 5], Item::withoutGlobalScopes()->bulkEligible()->orderBy('id')->pluck('id')->all());
    }

    public function test_postgres_query_uses_array_length_instead_of_unsupported_json_comparison(): void
    {
        config(['database.connections.pgsql_audit' => [
            'driver' => 'pgsql', 'host' => '127.0.0.1', 'database' => 'unused',
            'username' => 'unused', 'password' => '', 'prefix' => '',
        ]]);
        $query = (new Item)->setConnection('pgsql_audit')->newQueryWithoutScopes()->bulkEligible();
        $sql = $query->toSql();
        $this->assertStringContainsString('jsonb_typeof', $sql);
        $this->assertStringContainsString('jsonb_array_length', $sql);
        $this->assertStringNotContainsString('"bulk_pricing_tiers" !=', $sql);
    }
}
