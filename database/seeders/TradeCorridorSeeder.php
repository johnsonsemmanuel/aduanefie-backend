<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TradeCorridor;

class TradeCorridorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $corridors = [
            // West Africa (ECOWAS)
            ['origin_country' => 'Ghana', 'destination_country' => 'Nigeria', 'distance_km' => 650, 'transit_days_estimate' => 5, 'trucking_rate_per_ton_usd' => 180, 'minimum_charge_usd' => 400, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Ghana', 'destination_country' => 'Côte d\'Ivoire', 'distance_km' => 480, 'transit_days_estimate' => 3, 'trucking_rate_per_ton_usd' => 150, 'minimum_charge_usd' => 350, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Ghana', 'destination_country' => 'Burkina Faso', 'distance_km' => 850, 'transit_days_estimate' => 4, 'trucking_rate_per_ton_usd' => 160, 'minimum_charge_usd' => 380, 'cold_chain_available' => 0, 'warehousing_available' => 1],
            ['origin_country' => 'Nigeria', 'destination_country' => 'Benin', 'distance_km' => 110, 'transit_days_estimate' => 1, 'trucking_rate_per_ton_usd' => 120, 'minimum_charge_usd' => 300, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Nigeria', 'destination_country' => 'Niger', 'distance_km' => 1200, 'transit_days_estimate' => 6, 'trucking_rate_per_ton_usd' => 200, 'minimum_charge_usd' => 450, 'cold_chain_available' => 0, 'warehousing_available' => 0],
            ['origin_country' => 'Côte d\'Ivoire', 'destination_country' => 'Burkina Faso', 'distance_km' => 1100, 'transit_days_estimate' => 5, 'trucking_rate_per_ton_usd' => 170, 'minimum_charge_usd' => 400, 'cold_chain_available' => 0, 'warehousing_available' => 1],
            ['origin_country' => 'Senegal', 'destination_country' => 'Mali', 'distance_km' => 1200, 'transit_days_estimate' => 5, 'trucking_rate_per_ton_usd' => 180, 'minimum_charge_usd' => 420, 'cold_chain_available' => 0, 'warehousing_available' => 1],
            ['origin_country' => 'Senegal', 'destination_country' => 'Guinea', 'distance_km' => 700, 'transit_days_estimate' => 3, 'trucking_rate_per_ton_usd' => 140, 'minimum_charge_usd' => 340, 'cold_chain_available' => 1, 'warehousing_available' => 0],

            // East Africa (EAC)
            ['origin_country' => 'Kenya', 'destination_country' => 'Uganda', 'distance_km' => 500, 'transit_days_estimate' => 2, 'trucking_rate_per_ton_usd' => 140, 'minimum_charge_usd' => 320, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Kenya', 'destination_country' => 'Tanzania', 'distance_km' => 850, 'transit_days_estimate' => 3, 'trucking_rate_per_ton_usd' => 150, 'minimum_charge_usd' => 350, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Kenya', 'destination_country' => 'Rwanda', 'distance_km' => 1500, 'transit_days_estimate' => 4, 'trucking_rate_per_ton_usd' => 190, 'minimum_charge_usd' => 430, 'cold_chain_available' => 0, 'warehousing_available' => 1],
            ['origin_country' => 'Uganda', 'destination_country' => 'Rwanda', 'distance_km' => 450, 'transit_days_estimate' => 2, 'trucking_rate_per_ton_usd' => 130, 'minimum_charge_usd' => 310, 'cold_chain_available' => 0, 'warehousing_available' => 1],
            ['origin_country' => 'Tanzania', 'destination_country' => 'Zambia', 'distance_km' => 1000, 'transit_days_estimate' => 4, 'trucking_rate_per_ton_usd' => 170, 'minimum_charge_usd' => 390, 'cold_chain_available' => 0, 'warehousing_available' => 1],

            // Southern Africa (SADC)
            ['origin_country' => 'South Africa', 'destination_country' => 'Zimbabwe', 'distance_km' => 650, 'transit_days_estimate' => 2, 'trucking_rate_per_ton_usd' => 160, 'minimum_charge_usd' => 360, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'South Africa', 'destination_country' => 'Botswana', 'distance_km' => 400, 'transit_days_estimate' => 1, 'trucking_rate_per_ton_usd' => 130, 'minimum_charge_usd' => 310, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'South Africa', 'destination_country' => 'Mozambique', 'distance_km' => 600, 'transit_days_estimate' => 2, 'trucking_rate_per_ton_usd' => 150, 'minimum_charge_usd' => 340, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Zambia', 'destination_country' => 'Zimbabwe', 'distance_km' => 700, 'transit_days_estimate' => 2, 'trucking_rate_per_ton_usd' => 140, 'minimum_charge_usd' => 330, 'cold_chain_available' => 0, 'warehousing_available' => 1],

            // North/Central Africa
            ['origin_country' => 'Egypt', 'destination_country' => 'Sudan', 'distance_km' => 1300, 'transit_days_estimate' => 5, 'trucking_rate_per_ton_usd' => 200, 'minimum_charge_usd' => 450, 'cold_chain_available' => 1, 'warehousing_available' => 1],
            ['origin_country' => 'Morocco', 'destination_country' => 'Mauritania', 'distance_km' => 1700, 'transit_days_estimate' => 6, 'trucking_rate_per_ton_usd' => 220, 'minimum_charge_usd' => 500, 'cold_chain_available' => 0, 'warehousing_available' => 0],

            // Intra-regional (COMESA)
            ['origin_country' => 'Ethiopia', 'destination_country' => 'Kenya', 'distance_km' => 1000, 'transit_days_estimate' => 3, 'trucking_rate_per_ton_usd' => 160, 'minimum_charge_usd' => 370, 'cold_chain_available' => 0, 'warehousing_available' => 1],
            ['origin_country' => 'Ethiopia', 'destination_country' => 'Djibouti', 'distance_km' => 900, 'transit_days_estimate' => 3, 'trucking_rate_per_ton_usd' => 150, 'minimum_charge_usd' => 350, 'cold_chain_available' => 0, 'warehousing_available' => 0],
        ];

        foreach ($corridors as &$corridor) {
            $corridor['corridor_name'] = ucfirst($corridor['origin_country']) . ' → ' . ucfirst($corridor['destination_country']);
        }

        foreach ($corridors as $corridor) {
            TradeCorridor::updateOrInsert(
                ['origin_country' => $corridor['origin_country'], 'destination_country' => $corridor['destination_country']],
                $corridor
            );
        }
    }
}
