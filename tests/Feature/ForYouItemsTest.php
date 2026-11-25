<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\ItemController;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ForYouItemsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        $this->createTables();
    }

    private function createTables(): void
    {
        $tables = [
            'cache' => function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            },
            'business_settings' => function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            },
            'translations' => function (Blueprint $table) {
                $table->id();
                $table->string('translationable_type')->nullable();
                $table->unsignedBigInteger('translationable_id')->nullable();
                $table->string('locale')->nullable();
                $table->string('key')->nullable();
                $table->text('value')->nullable();
                $table->timestamps();
            },
            'storages' => function (Blueprint $table) {
                $table->id();
                $table->string('data_type')->nullable();
                $table->unsignedBigInteger('data_id')->nullable();
                $table->string('key')->nullable();
                $table->string('value')->nullable();
                $table->timestamps();
            },
            'zones' => function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->boolean('status')->default(true);
                $table->timestamps();
            },
            'modules' => function (Blueprint $table) {
                $table->id();
                $table->string('module_name')->nullable();
                $table->string('module_type')->default('grocery');
                $table->boolean('status')->default(true);
                $table->timestamps();
            },
            'module_zone' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('module_id');
                $table->unsignedBigInteger('zone_id');
                $table->timestamps();
            },
            'categories' => function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            },
            'units' => function (Blueprint $table) {
                $table->id();
                $table->string('unit')->nullable();
                $table->timestamps();
            },
            'stores' => function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('slug')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('module_id')->default(1);
                $table->integer('status')->default(1);
                $table->boolean('active')->default(true);
                $table->string('store_business_model')->default('commission');
                $table->decimal('tax', 8, 2)->default(0);
                $table->decimal('minimum_order', 10, 2)->default(0);
                $table->string('delivery_time')->nullable();
                $table->string('logo')->nullable();
                $table->string('cover_photo')->nullable();
                $table->timestamps();
            },
            'discounts' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->decimal('discount', 8, 2)->default(0);
                $table->timestamps();
            },
            'store_configs' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->boolean('halal_tag_status')->default(false);
                $table->timestamps();
            },
            'store_subscriptions' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('store_id')->nullable();
                $table->string('max_order')->default('unlimited');
                $table->integer('status')->default(1);
                $table->timestamps();
            },
            'add_ons' => function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->integer('status')->default(1);
                $table->timestamps();
            },
            'flash_sales' => function (Blueprint $table) {
                $table->id();
                $table->integer('is_publish')->default(0);
                $table->timestamp('start_date')->nullable();
                $table->timestamp('end_date')->nullable();
                $table->timestamps();
            },
            'flash_sale_items' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('flash_sale_id')->nullable();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->integer('status')->default(0);
                $table->integer('available_stock')->default(0);
                $table->timestamps();
            },
            'pharmacy_item_details' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->timestamps();
            },
            'ecommerce_item_details' => function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('item_id')->nullable();
                $table->timestamps();
            },
            'item_nutrition' => function (Blueprint $table) {
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('nutrition_id');
            },
            'nutritions' => function (Blueprint $table) {
                $table->id();
                $table->string('nutrition')->nullable();
                $table->timestamps();
            },
            'allergy_item' => function (Blueprint $table) {
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('allergy_id');
            },
            'allergies' => function (Blueprint $table) {
                $table->id();
                $table->string('allergy')->nullable();
                $table->timestamps();
            },
            'item_generic_names' => function (Blueprint $table) {
                $table->unsignedBigInteger('item_id');
                $table->unsignedBigInteger('generic_name_id');
            },
            'generic_names' => function (Blueprint $table) {
                $table->id();
                $table->string('generic_name')->nullable();
                $table->timestamps();
            },
            'items' => function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->nullable();
                $table->text('description')->nullable();
                $table->string('image')->nullable();
                $table->text('images')->nullable();
                $table->unsignedBigInteger('category_id')->nullable();
                $table->text('category_ids')->nullable();
                $table->text('variations')->nullable();
                $table->text('add_ons')->nullable();
                $table->text('attributes')->nullable();
                $table->text('choice_options')->nullable();
                $table->text('food_variations')->nullable();
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('tax', 8, 2)->default(0);
                $table->string('tax_type')->default('percent');
                $table->decimal('discount', 8, 2)->default(0);
                $table->string('discount_type')->default('percent');
                $table->unsignedBigInteger('store_id');
                $table->unsignedBigInteger('module_id')->default(1);
                $table->unsignedBigInteger('unit_id')->nullable();
                $table->integer('status')->default(1);
                $table->integer('is_approved')->default(1);
                $table->integer('veg')->default(1);
                $table->integer('recommended')->default(0);
                $table->integer('order_count')->default(0);
                $table->decimal('avg_rating', 4, 2)->default(0);
                $table->integer('rating_count')->default(0);
                $table->text('rating')->nullable();
                $table->integer('stock')->default(50);
                $table->boolean('is_farming_product')->default(false);
                $table->boolean('pre_order_available')->default(false);
                $table->boolean('group_buy_enabled')->default(false);
                $table->timestamps();
            },
        ];

        foreach ($tables as $name => $definition) {
            if (!Schema::hasTable($name)) {
                Schema::create($name, $definition);
            }
        }
    }

    private function seedActiveCatalog(): void
    {
        Zone::create(['id' => 3, 'name' => 'Accra Central', 'status' => 1]);
        Zone::create(['id' => 9, 'name' => 'Empty Zone', 'status' => 1]);
        Module::create(['id' => 1, 'module_name' => 'Nearby Food Market', 'module_type' => 'grocery', 'status' => 1]);
        DB::table('module_zone')->insert([
            ['module_id' => 1, 'zone_id' => 3],
            ['module_id' => 1, 'zone_id' => 9],
        ]);
        DB::table('categories')->insert(['id' => 10, 'name' => 'Fresh Produce', 'status' => 1]);

        $store = Store::create([
            'id' => 101,
            'name' => 'Volta Harvest Farm',
            'zone_id' => 3,
            'module_id' => 1,
            'status' => 1,
            'active' => 1,
            'store_business_model' => 'commission',
        ]);

        foreach ([
            ['id' => 1, 'name' => 'Organic Plantains', 'price' => 45, 'order_count' => 25, 'avg_rating' => 4.8, 'discount' => 10],
            ['id' => 2, 'name' => 'Fresh Cassava', 'price' => 30, 'order_count' => 12, 'avg_rating' => 4.5, 'discount' => 0],
            ['id' => 3, 'name' => 'Pona Yam', 'price' => 60, 'order_count' => 8, 'avg_rating' => 4.2, 'discount' => 0],
        ] as $row) {
            Item::create(array_merge($row, [
                'store_id' => $store->id,
                'module_id' => 1,
                'category_id' => 10,
                'category_ids' => json_encode([['id' => '10', 'position' => 1]]),
                'variations' => json_encode([]),
                'add_ons' => json_encode([]),
                'attributes' => json_encode([]),
                'choice_options' => json_encode([]),
                'food_variations' => json_encode([]),
                'status' => 1,
                'is_approved' => 1,
            ]));
        }

        config(['module.current_module_data' => ['id' => 1, 'module_type' => 'grocery']]);
    }

    public function test_for_you_requires_valid_zone_header(): void
    {
        $controller = new ItemController();

        $missing = $controller->for_you_items(Request::create('/api/v1/items/for-you', 'GET'));
        $this->assertSame(403, $missing->getStatusCode());

        $malformedReq = Request::create('/api/v1/items/for-you', 'GET');
        $malformedReq->headers->set('zoneId', 'not-a-zone');
        $malformed = $controller->for_you_items($malformedReq);
        $this->assertSame(403, $malformed->getStatusCode());
    }

    public function test_for_you_returns_200_with_scored_items_and_pagination_without_relation_error(): void
    {
        $this->seedActiveCatalog();
        $controller = new ItemController();

        // Page 1 (limit=2, offset=1)
        $req1 = Request::create('/api/v1/items/for-you', 'GET', ['limit' => 2, 'offset' => 1, 'interest_ids' => '10']);
        $req1->headers->set('zoneId', '[3]');
        $res1 = $controller->for_you_items($req1);
        $this->assertSame(200, $res1->getStatusCode());

        $body1 = $res1->getData(true);
        $this->assertSame(3, $body1['total_size']);
        $this->assertSame(2, $body1['limit']);
        $this->assertSame(1, $body1['offset']);
        $this->assertTrue($body1['personalized']);
        $this->assertCount(2, $body1['items']);
        $this->assertNotEmpty($body1['items'][0]['recommendation_reason']);

        // Page 2 (limit=2, offset=2) -> remaining 1 item
        $req2 = Request::create('/api/v1/items/for-you', 'GET', ['limit' => 2, 'offset' => 2]);
        $req2->headers->set('zoneId', '[3]');
        $res2 = $controller->for_you_items($req2);
        $this->assertSame(200, $res2->getStatusCode());
        $body2 = $res2->getData(true);
        $this->assertSame(3, $body2['total_size']);
        $this->assertCount(1, $body2['items']);
    }

    public function test_for_you_returns_empty_list_for_zone_with_no_active_items(): void
    {
        $this->seedActiveCatalog();
        $controller = new ItemController();

        $req = Request::create('/api/v1/items/for-you', 'GET', ['limit' => 10, 'offset' => 1]);
        $req->headers->set('zoneId', '[9]');
        $res = $controller->for_you_items($req);

        $this->assertSame(200, $res->getStatusCode());
        $body = $res->getData(true);
        $this->assertSame(0, $body['total_size']);
        $this->assertSame([], $body['items']);
    }
}
