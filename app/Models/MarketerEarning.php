<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketerEarning extends Model
{
    protected $fillable = ['marketer_id', 'amount', 'type', 'order_id', 'note', 'status'];

    public function marketer(): BelongsTo
    {
        return $this->belongsTo(Marketer::class);
    }
}
