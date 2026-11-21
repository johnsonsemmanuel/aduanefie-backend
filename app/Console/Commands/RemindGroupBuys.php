<?php

namespace App\Console\Commands;

use App\Models\GroupBuy;
use App\Services\GroupBuy\GroupBuyNotificationService;
use Illuminate\Console\Command;

class RemindGroupBuys extends Command
{
    protected $signature = 'group-buys:remind';

    protected $description = 'Send payment reminders to members of locked groups who have not yet paid';

    public function handle(GroupBuyNotificationService $notifications): int
    {
        $reminded = 0;

        foreach (GroupBuy::where('status', GroupBuy::STATUS_LOCKED)->get() as $group) {
            $notifications->paymentReminder($group);
            $reminded += $group->unpaidCount();
        }

        $this->info(sprintf('Payment reminders sent for %d unpaid shares.', $reminded));

        return self::SUCCESS;
    }
}
