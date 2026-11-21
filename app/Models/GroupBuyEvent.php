<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroupBuyEvent extends Model
{
    protected $guarded = ['id'];

    /**
     * Internal actor identifiers and free-form metadata are not meant for
     * public consumption. Keep them off serialised event rows even if a future
     * endpoint eager-loads this relation.
     */
    protected $hidden = [
        'actor_id',
        'participant_id',
        'meta',
    ];

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
