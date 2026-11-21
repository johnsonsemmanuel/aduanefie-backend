<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FarmPlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'store_id',
        'name',
        'plot_code',
        'size_acres',
        'crop_type',
        'variety',
        'planting_date',
        'expected_harvest_date',
        'status',
        'soil_type',
        'irrigation_method',
        'notes',
    ];

    protected $casts = [
        'size_acres' => 'float',
        'planting_date' => 'date',
        'expected_harvest_date' => 'date',
    ];

    protected $appends = ['days_to_harvest', 'cultivation_progress_pct'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class, 'plot_id');
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeCropType($query, $cropType)
    {
        return $query->where('crop_type', $cropType);
    }

    public function getDaysToHarvestAttribute(): ?int
    {
        if (!$this->expected_harvest_date) {
            return null;
        }
        return \Carbon\Carbon::parse($this->expected_harvest_date)->diffInDays(\Carbon\Carbon::now());
    }

    public function getCultivationProgressPctAttribute(): ?int
    {
        if (!$this->planting_date || !$this->expected_harvest_date) {
            return null;
        }

        $totalDays = \Carbon\Carbon::parse($this->planting_date)->diffInDays($this->expected_harvest_date);
        if ($totalDays <= 0) {
            return 100;
        }

        $elapsedDays = \Carbon\Carbon::parse($this->planting_date)->diffInDays(\Carbon\Carbon::now());
        return min(100, max(0, (int) round(($elapsedDays / $totalDays) * 100)));
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'planning' => 'Planning',
            'planting' => 'Planting',
            'growing' => 'Growing',
            'harvest_ready' => 'Harvest Ready',
            'fallow' => 'Fallow (Resting)',
            default => ucfirst($this->status),
        };
    }
}
