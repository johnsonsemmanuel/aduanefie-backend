<?php

namespace App\Console\Commands;

use App\Models\DeliveryMan;
use App\Models\Order;
use App\Models\BusinessSetting;
use App\CentralLogics\Helpers;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReleaseStuckCommunityAgents extends Command
{
    protected $signature = 'community-agent:release-stuck
                            {--threshold=240 : Minutes before an order is considered stuck (default: 240 = 4 hours)}
                            {--dry-run : Show what would be released without making changes}';

    protected $description = 'Auto-release community agents stuck on orders that exceed the timeout threshold';

    public function handle(): int
    {
        $thresholdMinutes = (int) (
            $this->option('threshold')
            ?? BusinessSetting::where('key', 'community_delivery_timeout')->first()?->value
            ?? 240
        );
        $dryRun = $this->option('dry-run');
        $cutoff = now()->subMinutes($thresholdMinutes);

        $stuckOrders = Order::where('is_community_delivery', 1)
            ->whereNotNull('community_agent_id')
            ->whereNotIn('order_status', ['delivered', 'canceled', 'failed'])
            ->where('updated_at', '<', $cutoff)
            ->get();

        if ($stuckOrders->isEmpty()) {
            $this->info('No stuck community delivery orders found.');
            return self::SUCCESS;
        }

        $this->info("Found {$stuckOrders->quantity} stuck community delivery order(s) (threshold: {$thresholdMinutes} min).");
        $this->newLine();

        $released = 0;

        foreach ($stuckOrders as $order) {
            $agent = DeliveryMan::find($order->community_agent_id);

            $this->line("Order #{$order->id} | Status: {$order->order_status} | Updated: {$order->updated_at} | Agent: " . ($agent ? "{$agent->f_name} {$agent->l_name} (ID: {$agent->id})" : 'unknown'));

            if ($dryRun) {
                $this->line('  [dry-run] Would release agent and log event.');
                continue;
            }

            DB::beginTransaction();
            try {
                if ($agent) {
                    $agent->current_orders = max(0, $agent->current_orders - 1);
                    $agent->save();
                }

                Log::warning('community_agent_timeout_release', [
                    'order_id' => $order->id,
                    'agent_id' => $order->community_agent_id,
                    'order_status' => $order->order_status,
                    'order_updated_at' => $order->updated_at,
                    'threshold_minutes' => $thresholdMinutes,
                    'released_at' => now()->toDateTimeString(),
                ]);

                DB::commit();
                $released++;

                $this->line('  Released.');
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("  Failed to release: {$e->getMessage()}");
                Log::error('community_agent_timeout_release_failed', [
                    'order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. Released {$released} of {$stuckOrders->quantity} agent(s).");

        return self::SUCCESS;
    }
}
