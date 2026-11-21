<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegionalTour extends Model
{
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_OPEN = 'open';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const GHANA_REGIONS = [
        'Greater Accra',
        'Ashanti',
        'Eastern',
        'Central',
        'Western',
        'Western North',
        'Volta',
        'Oti',
        'Bono',
        'Bono East',
        'Ahafo',
        'Northern',
        'Savannah',
        'North East',
        'Upper East',
        'Upper West',
    ];

    protected $fillable = [
        'title',
        'region',
        'district',
        'venue',
        'start_date',
        'end_date',
        'lead_marketer_id',
        'capacity',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'capacity' => 'integer',
    ];

    public function leadMarketer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'lead_marketer_id');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(RegionalTourRegistration::class, 'tour_id');
    }
}
