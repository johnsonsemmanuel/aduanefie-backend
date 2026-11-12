<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ExportDocument extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'document_type',
        'document_number',
        'certificate_file',
        'issued_by',
        'issued_at',
        'valid_until',
        'destination_country',
        'status',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'valid_until' => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
