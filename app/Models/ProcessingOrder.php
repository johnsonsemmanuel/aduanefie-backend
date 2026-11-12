<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProcessingOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'facility_id',
        'farmer_id',
        'raw_material',
        'raw_quantity',
        'raw_unit',
        'processed_product',
        'processed_quantity',
        'processed_unit',
        'processing_fee',
        'status',
        'submitted_at',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'raw_quantity' => 'float',
        'processed_quantity' => 'float',
        'processing_fee' => 'float',
        'submitted_at' => 'date',
        'started_at' => 'date',
        'completed_at' => 'date',
    ];

    public function facility()
    {
        return $this->belongsTo(ProcessingFacility::class);
    }

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }
}
