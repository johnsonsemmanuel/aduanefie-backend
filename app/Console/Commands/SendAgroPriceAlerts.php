<?php

namespace App\Console\Commands;

use App\CentralLogics\SMS_module;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAgroPriceAlerts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'agro:send-price-alerts {--phone= : Optional single phone number for testing}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Broadcast Ghana daily agro market prices via Hubtel SMS to active farms and stores';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $topPrices = DB::table('agro_market_prices')
            ->where('is_active', 1)
            ->orderByDesc('recorded_at')
            ->limit(4)
            ->get();

        if ($topPrices->isEmpty()) {
            $this->warn('No active agro market prices found.');
            return self::SUCCESS;
        }

        $parts = [];
        foreach ($topPrices as $row) {
            $parts[] = "{$row->commodity} ({$row->market_hub}): GHS {$row->wholesale_price_ghs}/{$row->unit}";
        }
        $message = 'Aduanefie Agro Index: ' . implode('; ', $parts);

        $phoneOption = $this->option('phone');
        if (!empty($phoneOption)) {
            $phones = [trim($phoneOption)];
        } else {
            $phones = DB::table('stores')
                ->where('status', 1)
                ->whereNotNull('phone')
                ->where('phone', '!=', '')
                ->distinct()
                ->pluck('phone')
                ->toArray();
        }

        if (empty($phones)) {
            $this->warn('No active store phone numbers found.');
            return self::SUCCESS;
        }

        $sent = 0;
        $failed = 0;
        foreach ($phones as $phone) {
            $res = SMS_module::send($phone, '', $message);
            if ($res === 'success') {
                $sent++;
            } else {
                $failed++;
            }
        }

        $this->info("Agro price SMS broadcast finished. Sent: {$sent}, Failed: {$failed}");
        if ($sent === 0 && $failed > 0) {
            $this->warn('All sends failed. Check that Hubtel SMS is enabled (status=1) in addon_settings.');
        }

        return self::SUCCESS;
    }
}
