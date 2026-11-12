<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProcessingFacility extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'facility_name',
        'facility_type',
        'capacity_per_day',
        'capacity_unit',
        'location',
        'contact_person',
        'contact_phone',
        'status',
    ];

    protected $casts = [
        'capacity_per_day' => 'float',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function processingOrders()
    {
        return $this->hasMany(ProcessingOrder::class);
    }
}
