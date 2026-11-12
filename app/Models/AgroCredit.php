<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AgroCredit extends Model
{
    use HasFactory;

    protected $table = 'agro_credit';

    protected $fillable = [
        'farmer_id',
        'store_id',
        'credit_type',
        'amount',
        'interest_rate',
        'term_months',
        'purpose',
        'status',
        'applied_at',
        'approved_at',
        'disbursed_at',
        'repaid_at',
    ];

    protected $casts = [
        'amount' => 'float',
        'interest_rate' => 'float',
        'term_months' => 'integer',
        'applied_at' => 'date',
        'approved_at' => 'date',
        'disbursed_at' => 'date',
        'repaid_at' => 'date',
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
