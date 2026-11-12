<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DataSetting;

class AgriTradePositioningSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $settings = [
            'fixed_header_title' => 'Grown in Africa. Traded in Africa.',
            'fixed_header_sub_title' => 'The premier marketplace for intra-African agricultural trade. Connecting farmers, co-ops, and buyers across borders.',
            'fixed_module_title' => 'African Agricultural Markets',
            'fixed_module_sub_title' => 'Discover agro-commodities by origin country, destination country, and commodity. Trade across ECOWAS, SADC, EAC, and COMESA corridors.',
            'fixed_referal_title' => 'Invite African Traders',
            'fixed_referal_sub_title' => 'Build the largest intra-African agri network. Refer farmers, buyers, and agribusinesses to Aduanefie.',
            'available_zone_title' => 'Trade Corridors',
            'available_zone_short_description' => 'Browse agricultural products moving across African borders. Origin to destination, farm to market.',
            'feature_title' => 'How Cross-Border Agri Trade Works',
            'feature_short_description' => 'Find verified African sellers, request quotes, agree incoterms, and settle in local currencies with escrow protection.',
        ];

        foreach ($settings as $key => $value) {
            DataSetting::updateOrInsert(
                ['key' => $key, 'type' => 'admin_landing_page'],
                ['value' => $value]
            );
        }
    }
}
