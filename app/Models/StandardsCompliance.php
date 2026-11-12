<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StandardsCompliance extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'standard_name',
        'certificate_number',
        'issuing_body',
        'issue_date',
        'expiry_date',
        'certificate_file',
        'status',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
