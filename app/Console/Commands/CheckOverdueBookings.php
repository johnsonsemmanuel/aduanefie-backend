<?php

namespace App\Console\Commands;

use App\Models\EquipmentBooking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckOverdueBookings extends Command
{
    protected $signature = 'equipment:check-overdue
                            {--dry-run : Show what would be flipped without making changes}';

    protected $description = 'Flip active equipment bookings to overdue once end_date has passed';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $overdueBookings = EquipmentBooking::where('status', 'active')
            ->where('end_date', '<', now())
            ->get();

        if ($overdueBookings->isEmpty()) {
            $this->info('No overdue equipment bookings found.');
            return self::SUCCESS;
        }

        $this->info("Found {$overdueBookings->quantity} overdue booking(s).");
        $this->newLine();

        $flipped = 0;

        foreach ($overdueBookings as $booking) {
            $this->line("Booking #{$booking->id} | Item: {$booking->item_id} | End: {$booking->end_date}");

            if ($dryRun) {
                $this->line('  [dry-run] Would flip to overdue.');
                continue;
            }

            try {
                $booking->status = 'overdue';
                $booking->save();

                Log::warning('equipment_booking_overdue', [
                    'booking_id' => $booking->id,
                    'item_id' => $booking->item_id,
                    'customer_id' => $booking->customer_id,
                    'end_date' => $booking->end_date->toDateTimeString(),
                    'flipped_at' => now()->toDateTimeString(),
                ]);

                $flipped++;
                $this->line('  Flipped to overdue.');
            } catch (\Throwable $e) {
                $this->error("  Failed: {$e->getMessage()}");
                Log::error('equipment_booking_overdue_failed', [
                    'booking_id' => $booking->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->newLine();
        $this->info("Done. Flipped {$flipped} of {$overdueBookings->quantity} booking(s).");

        return self::SUCCESS;
    }
}
