<?php

namespace App\Console\Commands;

use App\Models\GroupBuy;
use App\Services\GroupBuy\GroupBuyNotificationService;
use App\Services\GroupBuy\GroupBuyService;
use Illuminate\Console\Command;

class ExpireGroupBuys extends Command
{
    protected $signature = 'group-buys:expire';

    protected $description = 'Expire open group buys whose deadline passed and release their members';

    public function handle(GroupBuyService $groups, GroupBuyNotificationService $notifications): int
    {
        $expired = GroupBuy::where('status', GroupBuy::STATUS_OPEN)
            ->whereNotNull('closes_at')
            ->where('closes_at', '<=', now())
            ->get();

        foreach ($expired as $group) {
            // No payment is captured at join, so releasing members is the whole
            // refund story: nobody was charged.
            $groups->expire($group);
            $notifications->groupReleased($group, 'expired');
        }

        $this->info(sprintf('Group buys expired: %d.', $expired->count()));

        return self::SUCCESS;
    }
}
