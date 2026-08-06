<?php

namespace App\Services;

use App\CentralLogics\Helpers;
use App\Models\EquipmentBooking;
use Illuminate\Support\Facades\DB;

class EquipmentBookingNotifier
{
    public static function notify(EquipmentBooking $booking, string $event, string $actor = 'system'): void
    {
        $booking->loadMissing(['store.vendor', 'customer']);

        $customerText = self::customerText($event);
        $vendorText = self::vendorText($event, $actor);

        if ($customerText !== null && $booking->customer) {
            $token = $booking->customer->cm_firebase_token;
            if (Helpers::getNotificationStatusData('customer', 'customer_order_notification', 'push_notification_status') && $token) {
                $data = [
                    'title' => translate('Equipment_Booking'),
                    'description' => $customerText,
                    'image' => '',
                    'type' => 'equipment_booking',
                    'booking_id' => $booking->id,
                ];
                Helpers::send_push_notif_to_device($token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'user_id' => $booking->customer_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if ($vendorText !== null && $booking->store && $booking->store->vendor) {
            $storeId = $booking->store_id;
            $token = $booking->store->vendor->firebase_token;
            if (Helpers::getNotificationStatusData('store', 'store_order_notification', 'push_notification_status', $storeId) && $token) {
                $data = [
                    'title' => translate('Equipment_Booking'),
                    'description' => $vendorText,
                    'image' => '',
                    'type' => 'equipment_booking',
                    'booking_id' => $booking->id,
                ];
                Helpers::send_push_notif_to_device($token, $data);
                DB::table('user_notifications')->insert([
                    'data' => json_encode($data),
                    'vendor_id' => $booking->store->vendor_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private static function customerText(string $event): ?string
    {
        return match ($event) {
            'created' => translate('Your_equipment_rental_request_has_been_submitted_and_is_awaiting_confirmation'),
            'confirmed' => translate('Your_equipment_rental_booking_has_been_confirmed'),
            'cancelled' => translate('Your_equipment_rental_booking_has_been_cancelled'),
            'active' => translate('Your_equipment_rental_booking_is_now_active'),
            'completed' => translate('Your_equipment_rental_booking_has_been_completed'),
            'overdue' => translate('Your_equipment_rental_period_has_ended_please_return_the_equipment'),
            default => null,
        };
    }

    private static function vendorText(string $event, string $actor): ?string
    {
        return match ($event) {
            'created' => translate('You_have_a_new_equipment_rental_request'),
            'cancelled' => $actor === 'customer'
                ? translate('An_equipment_rental_booking_has_been_cancelled_by_the_customer')
                : null,
            default => null,
        };
    }
}
