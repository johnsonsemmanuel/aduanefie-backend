<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\CentralLogics\Helpers;

class TradeDocument extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
        'status' => 'string',
    ];

    protected $appends = ['file_full_url'];

    public function tradeRequest(): BelongsTo
    {
        return $this->belongsTo(TradeRequest::class, 'trade_request_id');
    }

    public function tradeQuote(): BelongsTo
    {
        return $this->belongsTo(TradeRequestQuote::class, 'trade_quote_id');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('document_type', $type);
    }

    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    public function getFileFullUrlAttribute()
    {
        return Helpers::get_full_url('trade_document', $this->file_path, $this->storage);
    }

    public static function documentTypes(): array
    {
        return [
            'sps_certificate' => 'SPS Certificate',
            'phytosanitary' => 'Phytosanitary Certificate',
            'origin_certificate' => 'Certificate of Origin',
            'export_permit' => 'Export Permit',
            'other' => 'Other',
        ];
    }

    public function getTypeLabelAttribute()
    {
        $types = self::documentTypes();
        return $types[$this->document_type] ?? $this->document_type;
    }

    public function isExpired(): bool
    {
        if (!$this->expiry_date) {
            return false;
        }
        return $this->expiry_date < now();
    }
}
