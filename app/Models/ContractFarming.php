<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ContractFarming extends Model
{
    use HasFactory;

    protected $table = 'contract_farming';

    protected $fillable = [
        'farmer_id',
        'buyer_id',
        'crop_type',
        'acreage',
        'expected_yield',
        'unit',
        'agreed_price',
        'currency_code',
        'planting_date',
        'expected_harvest_date',
        'actual_harvest_date',
        'contract_file',
        'status',
    ];

    protected $casts = [
        'acreage' => 'float',
        'expected_yield' => 'float',
        'agreed_price' => 'float',
        'planting_date' => 'date',
        'expected_harvest_date' => 'date',
        'actual_harvest_date' => 'date',
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
