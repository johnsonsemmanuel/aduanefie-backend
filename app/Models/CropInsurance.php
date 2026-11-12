<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CropInsurance extends Model
{
    use HasFactory;

    protected $table = 'crop_insurance';

    protected $fillable = [
        'farmer_id',
        'store_id',
        'policy_number',
        'crop_type',
        'insured_value',
        'premium_amount',
        'coverage_type',
        'risk_covered',
        'start_date',
        'end_date',
        'status',
    ];

    protected $casts = [
        'insured_value' => 'float',
        'premium_amount' => 'float',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }
}
