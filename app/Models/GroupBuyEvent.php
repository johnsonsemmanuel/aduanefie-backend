<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupBuyEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'group_buy_id' => 'integer',
        'participant_id' => 'integer',
        'actor_id' => 'integer',
        'meta' => 'array',
    ];

    public function groupBuy(): BelongsTo
    {
        return $this->belongsTo(GroupBuy::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(GroupBuyParticipant::class);
    }
}
