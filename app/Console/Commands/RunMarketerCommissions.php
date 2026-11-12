<?php

namespace App\Console\Commands;

use App\Services\MarketerCommissionService;
use Illuminate\Console\Command;

/**
 * Creates missing marketer commissions, voids commissions for canceled or
 * refunded orders, and releases commissions whose 14-day hold has matured.
 *
 * Intentionally a scan rather than a hook on the order-delivered code path:
 * an order can be delivered from the rider API, the vendor panel, the vendor
 * API, POS or the admin panel. Hooking five places means a sixth path added
 * later silently loses money. The unique index on order_id makes the scan
 * idempotent.
 */
class RunMarketerCommissions extends Command
{
    protected $signature = 'marketer:accrue';

    protected $description = 'Accrue, reconcile and release marketer commissions';

    public function handle(MarketerCommissionService $service): int
    {
        $created = $service->accrue();
        $result = $service->refresh();

        $this->info(sprintf(
            'Marketer commissions: %d accrued, %d voided, %d held, %d restored, %d released.',
            $created,
            $result['voided'],
            $result['held'],
            $result['restored'],
            $result['released']
        ));

        return self::SUCCESS;
    }
}
