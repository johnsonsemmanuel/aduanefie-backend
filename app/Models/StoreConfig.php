<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class StoreConfig extends Model
{
    use HasFactory;

    protected $table;
    protected static ?string $resolvedTable = null;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        if (static::$resolvedTable === null) {
            static::$resolvedTable = Cache::remember('store_config_table_name_v1', now()->addHours(12), function () {
                return Schema::hasTable('storeConfigs') ? 'storeConfigs' : 'store_configs';
            });
        }
        $this->table = static::$resolvedTable;
    }

    protected $guarded = ['id'];

    protected $casts = [
        'store_id' => 'integer',
        'minimum_stock_for_warning' => 'integer',
        'is_recommended' => 'boolean',
        'is_recommended_deleted' => 'boolean',
        'halal_tag_status' => 'boolean',
        'extra_packaging_status' => 'boolean',
        'extra_packaging_amount' => 'float',
    ];

    public function Store()
    {
        return $this->belongsTo(Store::class);
    }
}
