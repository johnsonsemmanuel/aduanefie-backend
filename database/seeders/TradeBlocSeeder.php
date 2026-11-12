<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TradeBloc;

class TradeBlocSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $blocs = [
            [
                'name' => 'Economic Community of West African States (ECOWAS)',
                'slug' => 'ecowas',
                'description' => 'West African trade bloc promoting economic integration among 15 member states.',
                'member_countries' => json_encode([
                    'Benin', 'Burkina Faso', 'Côte d\'Ivoire', 'Gambia', 'Ghana', 'Guinea',
                    'Guinea-Bissau', 'Liberia', 'Mali', 'Niger', 'Nigeria', 'Senegal',
                    'Sierra Leone', 'Togo', 'Cape Verde',
                ]),
                'primary_currencies' => json_encode(['XOF', 'XAF', 'GHS', 'NGN', 'GMD', 'GNF', 'LRD', 'MWK', 'SLL', 'CVE']),
                'common_languages' => json_encode(['English', 'French', 'Portuguese']),
                'website' => 'https://ecowas.int',
                'logo' => 'blocs/ecowas.png',
            ],
            [
                'name' => 'Southern African Development Community (SADC)',
                'slug' => 'sadc',
                'description' => 'Southern African trade bloc promoting economic development and integration.',
                'member_countries' => json_encode([
                    'Angola', 'Botswana', 'Comoros', 'DRC', 'Eswatini', 'Lesotho',
                    'Madagascar', 'Malawi', 'Mauritius', 'Mozambique', 'Namibia',
                    'Seychelles', 'South Africa', 'Tanzania', 'Zambia', 'Zimbabwe',
                ]),
                'primary_currencies' => json_encode(['ZAR', 'BWP', 'MZN', 'ZMW', 'MWK', 'NAD', 'LSL', 'SZL', 'MUR', 'SCR']),
                'common_languages' => json_encode(['English', 'Portuguese', 'French']),
                'website' => 'https://www.sadc.int',
                'logo' => 'blocs/sadc.png',
            ],
            [
                'name' => 'East African Community (EAC)',
                'slug' => 'eac',
                'description' => 'East African trade bloc promoting economic integration among 8 partner states.',
                'member_countries' => json_encode([
                    'Burundi', 'DRC', 'Kenya', 'Rwanda', 'South Sudan',
                    'Tanzania', 'Uganda',
                ]),
                'primary_currencies' => json_encode(['KES', 'UGX', 'TZS', 'RWF', 'BIF', 'SSP', 'CDF']),
                'common_languages' => json_encode(['English', 'Swahili', 'French']),
                'website' => 'https://www.eac.int',
                'logo' => 'blocs/eac.png',
            ],
            [
                'name' => 'Common Market for Eastern and Southern Africa (COMESA)',
                'slug' => 'comesa',
                'description' => 'African trade bloc promoting economic integration among 21 member states.',
                'member_countries' => json_encode([
                    'Burundi', 'Comoros', 'DRC', 'Djibouti', 'Egypt', 'Eritrea',
                    'Eswatini', 'Ethiopia', 'Kenya', 'Libya', 'Madagascar', 'Malawi',
                    'Mauritius', 'Rwanda', 'Seychelles', 'Somalia', 'Sudan',
                    'Tunisia', 'Uganda', 'Zambia', 'Zimbabwe',
                ]),
                'primary_currencies' => json_encode(['KES', 'UGX', 'TZS', 'ETB', 'RWF', 'BIF', 'SSP', 'EGP', 'SDG', 'MWK', 'MZN', 'ZMW', 'ZWL', 'MUR', 'SCR']),
                'common_languages' => json_encode(['English', 'French', 'Arabic', 'Portuguese']),
                'website' => 'https://www.comesa.int',
                'logo' => 'blocs/comesa.png',
            ],
        ];

        foreach ($blocs as $bloc) {
            TradeBloc::updateOrInsert(
                ['slug' => $bloc['slug']],
                $bloc
            );
        }
    }
}
