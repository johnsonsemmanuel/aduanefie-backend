<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GroupBuyInvite extends Model
{
    protected $fillable = [
        'group_buy_id',
        'sender_user_id',
        'email',
        'message',
    ];
}
