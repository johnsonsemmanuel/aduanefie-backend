<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PestReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'vendor_id',
        'pest_type',
        'severity',
        'description',
        'photos',
        'status',
        'ai_recommendation',
        'treatment_applied',
        'treated_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'severity' => 'string',
        'status' => 'string',
        'treated_at' => 'datetime',
    ];

    protected $appends = ['severity_label', 'status_label'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function getSeverityLabelAttribute(): string
    {
        return match ($this->severity) {
            'low' => 'Low Risk',
            'medium' => 'Moderate Risk',
            'high' => 'High Risk',
            'critical' => 'Critical - Immediate Action',
            default => ucfirst($this->severity),
        };
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'reported' => 'Reported',
            'treating' => 'Treating',
            'resolved' => 'Resolved',
            default => ucfirst($this->status),
        };
    }

    public function scopeSeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
