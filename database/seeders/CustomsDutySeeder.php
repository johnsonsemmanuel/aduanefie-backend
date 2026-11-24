<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CustomsDuty;

class CustomsDutySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // Common agricultural HS codes and duty rates for major African destination countries
        // HS chapters: 10 (cereals), 12 (oil seeds), 07 (vegetables), 08 (fruit), 15 (oils/fats), 01 (live animals), 02 (meat), 17 (sugar)
        $duties = [
            // Nigeria - ECOWAS common external tariff
            ['Nigeria', '10', 'Cereals', 5, 7.5, 0, 0, 1, 1],
            ['Nigeria', '07', 'Vegetables', 5, 7.5, 0, 0, 1, 1],
            ['Nigeria', '08', 'Fruit and nuts', 5, 7.5, 0, 0, 1, 1],
            ['Nigeria', '12', 'Oil seeds and oleaginous fruits', 5, 7.5, 0, 0, 1, 1],
            ['Nigeria', '15', 'Animal or vegetable fats and oils', 5, 7.5, 0, 0, 1, 1],
            ['Nigeria', '01', 'Live animals', 5, 7.5, 0, 1, 1, 1],
            ['Nigeria', '02', 'Meat and edible meat offal', 5, 7.5, 0, 1, 1, 1],
            ['Nigeria', '17', 'Sugars and sugar confectionery', 5, 7.5, 0, 0, 1, 1],

            // Ghana - ECOWAS common external tariff
            ['Ghana', '10', 'Cereals', 5, 8, 0, 0, 1, 1],
            ['Ghana', '07', 'Vegetables', 5, 8, 0, 0, 1, 1],
            ['Ghana', '08', 'Fruit and nuts', 5, 8, 0, 0, 1, 1],
            ['Ghana', '12', 'Oil seeds and oleaginous fruits', 5, 8, 0, 0, 1, 1],
            ['Ghana', '15', 'Animal or vegetable fats and oils', 5, 8, 0, 0, 1, 1],
            ['Ghana', '01', 'Live animals', 5, 8, 0, 1, 1, 1],
            ['Ghana', '02', 'Meat and edible meat offal', 5, 8, 0, 1, 1, 1],
            ['Ghana', '17', 'Sugars and sugar confectionery', 5, 8, 0, 0, 1, 1],

            // Kenya - EAC common external tariff
            ['Kenya', '10', 'Cereals', 10, 16, 0, 0, 1, 1],
            ['Kenya', '07', 'Vegetables', 10, 16, 0, 0, 1, 1],
            ['Kenya', '08', 'Fruit and nuts', 10, 16, 0, 0, 1, 1],
            ['Kenya', '12', 'Oil seeds and oleaginous fruits', 10, 16, 0, 0, 1, 1],
            ['Kenya', '15', 'Animal or vegetable fats and oils', 10, 16, 0, 0, 1, 1],
            ['Kenya', '01', 'Live animals', 10, 16, 0, 1, 1, 1],
            ['Kenya', '02', 'Meat and edible meat offal', 10, 16, 0, 1, 1, 1],
            ['Kenya', '17', 'Sugars and sugar confectionery', 10, 16, 0, 0, 1, 1],

            // South Africa - SADC
            ['South Africa', '10', 'Cereals', 0, 15, 0, 0, 1, 1],
            ['South Africa', '07', 'Vegetables', 0, 15, 0, 0, 1, 1],
            ['South Africa', '08', 'Fruit and nuts', 0, 15, 0, 0, 1, 1],
            ['South Africa', '12', 'Oil seeds and oleaginous fruits', 0, 15, 0, 0, 1, 1],
            ['South Africa', '15', 'Animal or vegetable fats and oils', 0, 15, 0, 0, 1, 1],
            ['South Africa', '01', 'Live animals', 0, 15, 0, 1, 1, 1],
            ['South Africa', '02', 'Meat and edible meat offal', 0, 15, 0, 1, 1, 1],
            ['South Africa', '17', 'Sugars and sugar confectionery', 0, 15, 0, 0, 1, 1],

            // Côte d'Ivoire - ECOWAS / WAEMU
            ["Côte d'Ivoire", '10', 'Cereals', 5, 18, 0, 0, 1, 1],
            ["Côte d'Ivoire", '07', 'Vegetables', 5, 18, 0, 0, 1, 1],
            ["Côte d'Ivoire", '08', 'Fruit and nuts', 5, 18, 0, 0, 1, 1],
            ["Côte d'Ivoire", '12', 'Oil seeds and oleaginous fruits', 5, 18, 0, 0, 1, 1],
            ["Côte d'Ivoire", '15', 'Animal or vegetable fats and oils', 5, 18, 0, 0, 1, 1],
            ["Côte d'Ivoire", '01', 'Live animals', 5, 18, 0, 1, 1, 1],
            ["Côte d'Ivoire", '02', 'Meat and edible meat offal', 5, 18, 0, 1, 1, 1],
            ["Côte d'Ivoire", '17', 'Sugars and sugar confectionery', 5, 18, 0, 0, 1, 1],

            // Egypt
            ['Egypt', '10', 'Cereals', 2, 14, 0, 0, 1, 1],
            ['Egypt', '07', 'Vegetables', 2, 14, 0, 0, 1, 1],
            ['Egypt', '08', 'Fruit and nuts', 2, 14, 0, 0, 1, 1],
            ['Egypt', '12', 'Oil seeds and oleaginous fruits', 2, 14, 0, 0, 1, 1],
            ['Egypt', '15', 'Animal or vegetable fats and oils', 2, 14, 0, 0, 1, 1],
            ['Egypt', '01', 'Live animals', 2, 14, 0, 1, 1, 1],
            ['Egypt', '02', 'Meat and edible meat offal', 2, 14, 0, 1, 1, 1],
            ['Egypt', '17', 'Sugars and sugar confectionery', 2, 14, 0, 0, 1, 1],

            // Tanzania - EAC
            ['Tanzania', '10', 'Cereals', 10, 18, 0, 0, 1, 1],
            ['Tanzania', '07', 'Vegetables', 10, 18, 0, 0, 1, 1],
            ['Tanzania', '08', 'Fruit and nuts', 10, 18, 0, 0, 1, 1],
            ['Tanzania', '12', 'Oil seeds and oleaginous fruits', 10, 18, 0, 0, 1, 1],
            ['Tanzania', '15', 'Animal or vegetable fats and oils', 10, 18, 0, 0, 1, 1],
            ['Tanzania', '01', 'Live animals', 10, 18, 0, 1, 1, 1],
            ['Tanzania', '02', 'Meat and edible meat offal', 10, 18, 0, 1, 1, 1],
            ['Tanzania', '17', 'Sugars and sugar confectionery', 10, 18, 0, 0, 1, 1],

            // Uganda - EAC
            ['Uganda', '10', 'Cereals', 10, 18, 0, 0, 1, 1],
            ['Uganda', '07', 'Vegetables', 10, 18, 0, 0, 1, 1],
            ['Uganda', '08', 'Fruit and nuts', 10, 18, 0, 0, 1, 1],
            ['Uganda', '12', 'Oil seeds and oleaginous fruits', 10, 18, 0, 0, 1, 1],
            ['Uganda', '15', 'Animal or vegetable fats and oils', 10, 18, 0, 0, 1, 1],
            ['Uganda', '01', 'Live animals', 10, 18, 0, 1, 1, 1],
            ['Uganda', '02', 'Meat and edible meat offal', 10, 18, 0, 1, 1, 1],
            ['Uganda', '17', 'Sugars and sugar confectionery', 10, 18, 0, 0, 1, 1],

            // Zambia - SADC
            ['Zambia', '10', 'Cereals', 5, 16, 0, 0, 1, 1],
            ['Zambia', '07', 'Vegetables', 5, 16, 0, 0, 1, 1],
            ['Zambia', '08', 'Fruit and nuts', 5, 16, 0, 0, 1, 1],
            ['Zambia', '12', 'Oil seeds and oleaginous fruits', 5, 16, 0, 0, 1, 1],
            ['Zambia', '15', 'Animal or vegetable fats and oils', 5, 16, 0, 0, 1, 1],
            ['Zambia', '01', 'Live animals', 5, 16, 0, 1, 1, 1],
            ['Zambia', '02', 'Meat and edible meat offal', 5, 16, 0, 1, 1, 1],
            ['Zambia', '17', 'Sugars and sugar confectionery', 5, 16, 0, 0, 1, 1],
        ];

        foreach ($duties as [$country, $hsCode, $description, $dutyRate, $vatRate, $exciseRate, $sps, $phyto, $origin]) {
            CustomsDuty::updateOrInsert(
                ['destination_country' => $country, 'hs_code' => $hsCode],
                [
                    'hs_description' => $description,
                    'duty_rate_percent' => $dutyRate,
                    'vat_rate_percent' => $vatRate,
                    'excise_rate_percent' => $exciseRate,
                    'requires_sps_certificate' => $sps,
                    'requires_phytosanitary' => $phyto,
                    'requires_origin_certificate' => $origin,
                ]
            );
        }
    }
}
