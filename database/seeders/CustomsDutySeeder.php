<?php

namespace Database\Seeders;

use App\Models\CustomsDuty;
use Illuminate\Database\Seeder;

class CustomsDutySeeder extends Seeder
{
    /**
     * Destination-wide defaults plus common agricultural HS chapters.
     *
     * Country keys are ISO 3166-1 alpha-2 codes. The resolver also matches the
     * legacy full-name rows already present in deployments, so this seeder is
     * safe to run on an existing database.
     */
    public function run()
    {
        $countries = [
            'GH' => ['name' => 'Ghana', 'duty' => 5, 'vat' => 8],
            'NG' => ['name' => 'Nigeria', 'duty' => 5, 'vat' => 7.5],
            'KE' => ['name' => 'Kenya', 'duty' => 10, 'vat' => 16],
            'ZA' => ['name' => 'South Africa', 'duty' => 0, 'vat' => 15],
            'CI' => ['name' => "Côte d'Ivoire", 'duty' => 5, 'vat' => 18],
            'TG' => ['name' => 'Togo', 'duty' => 5, 'vat' => 18],
            'BF' => ['name' => 'Burkina Faso', 'duty' => 5, 'vat' => 18],
            'SN' => ['name' => 'Senegal', 'duty' => 5, 'vat' => 18],
            'BJ' => ['name' => 'Benin', 'duty' => 5, 'vat' => 18],
            'ML' => ['name' => 'Mali', 'duty' => 5, 'vat' => 18],
            'EG' => ['name' => 'Egypt', 'duty' => 2, 'vat' => 14],
            'TZ' => ['name' => 'Tanzania', 'duty' => 10, 'vat' => 18],
            'UG' => ['name' => 'Uganda', 'duty' => 10, 'vat' => 18],
            'ZM' => ['name' => 'Zambia', 'duty' => 5, 'vat' => 16],
        ];

        $chapters = [
            '10' => 'Cereals',
            '07' => 'Vegetables',
            '08' => 'Fruit and nuts',
            '12' => 'Oil seeds and oleaginous fruits',
            '15' => 'Animal or vegetable fats and oils',
            '01' => 'Live animals',
            '02' => 'Meat and edible meat offal',
            '17' => 'Sugars and sugar confectionery',
        ];

        foreach ($countries as $code => $country) {
            $rows = [[
                'destination_country' => $code,
                'hs_code' => '*',
                'hs_description' => $country['name'].' default agricultural duty',
                'duty_rate_percent' => $country['duty'],
                'vat_rate_percent' => $country['vat'],
                'excise_rate_percent' => 0,
                'requires_sps_certificate' => 0,
                'requires_phytosanitary' => 1,
                'requires_origin_certificate' => 1,
            ]];

            foreach ($chapters as $hsCode => $description) {
                $isAnimal = in_array($hsCode, ['01', '02'], true);
                $rows[] = [
                    'destination_country' => $code,
                    'hs_code' => $hsCode,
                    'hs_description' => $description,
                    'duty_rate_percent' => $country['duty'],
                    'vat_rate_percent' => $country['vat'],
                    'excise_rate_percent' => 0,
                    'requires_sps_certificate' => $isAnimal ? 1 : 0,
                    'requires_phytosanitary' => $isAnimal ? 0 : 1,
                    'requires_origin_certificate' => 1,
                ];
            }

            foreach ($rows as $row) {
                CustomsDuty::updateOrInsert(
                    ['destination_country' => $row['destination_country'], 'hs_code' => $row['hs_code']],
                    $row
                );
            }
        }
    }
}
