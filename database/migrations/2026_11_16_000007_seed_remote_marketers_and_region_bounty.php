<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('admins')) {
            Schema::table('admins', function (Blueprint $table) {
                if (!Schema::hasColumn('admins', 'marketer_code')) {
                    $table->string('marketer_code', 32)->nullable();
                }
                if (!Schema::hasColumn('admins', 'marketer_region')) {
                    $table->string('marketer_region', 100)->nullable();
                }
                if (!Schema::hasColumn('admins', 'onboarding_bounty_ghs')) {
                    $table->decimal('onboarding_bounty_ghs', 10, 2)->nullable();
                }
            });
        }

        $roleId = (int) config('marketer.role_id', 5);
        if (Schema::hasTable('admin_roles') && !DB::table('admin_roles')->where('id', $roleId)->exists()) {
            DB::table('admin_roles')->insert([
                'id' => $roleId,
                'name' => 'Remote Marketer',
                'modules' => json_encode([]),
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (!Schema::hasTable('admins')) {
            return;
        }

        $rows = self::marketerDataset();
        $defaultPasswordHash = Hash::make('Aduanefie@2026');
        $regionCodes = [
            'Eastern Region' => 'ER',
            'Greater Accra Region' => 'GA',
            'Volta Region' => 'VR',
            'Ashanti Region' => 'AS',
            'North East Region' => 'NE',
            'Western Region' => 'WR',
            'Upper West Region' => 'UW',
            'Central Region' => 'CR',
            'Upper East Region' => 'UE',
            'Bono Region' => 'BR',
            'Savannah Region' => 'SR',
            'Northern Region' => 'NR',
            'Oti Region' => 'OT',
            'Western North Region' => 'WN',
        ];

        foreach ($rows as $idx => $r) {
            $fullName = trim(preg_replace('/\s+/', ' ', $r[0]));
            $rawPhone = trim($r[1]);
            $email = strtolower(trim($r[2]));
            $region = trim($r[3]);

            if ($email === '') {
                continue;
            }

            $parts = explode(' ', $fullName, 2);
            $fName = substr($parts[0] ?? 'Marketer', 0, 95);
            $lName = substr($parts[1] ?? '', 0, 95);

            // Normalize phone so it fits VARCHAR(20) and stays clean
            $cleanPhone = $rawPhone;
            if (strpos($cleanPhone, '/') !== false) {
                $cleanPhone = trim(explode('/', $cleanPhone)[0]);
            }
            // If contact column contained a street address instead of digits, leave phone blank
            if (preg_match('/[a-zA-Z]{3,}/', $cleanPhone)) {
                $cleanPhone = '';
            } else {
                $cleanPhone = preg_replace('/[^0-9+]/', '', $cleanPhone);
            }
            if ($cleanPhone === '') {
                // Ensure non-null unique placeholder if phone is required
                $cleanPhone = '000000' . str_pad((string) ($idx + 1), 4, '0', STR_PAD_LEFT);
            }
            $cleanPhone = substr($cleanPhone, 0, 20);

            $regPrefix = $regionCodes[$region] ?? 'GH';
            $code = 'MKT-' . $regPrefix . '-' . str_pad((string) ($idx + 1), 3, '0', STR_PAD_LEFT);

            $existing = DB::table('admins')->where('email', $email)->first();
            if ($existing) {
                DB::table('admins')->where('id', $existing->id)->update([
                    'f_name' => $fName,
                    'l_name' => $lName,
                    'phone' => $cleanPhone !== '' ? $cleanPhone : $existing->phone,
                    'role_id' => $roleId,
                    'marketer_region' => $region,
                    'marketer_code' => $existing->marketer_code ?: $code,
                    'updated_at' => now(),
                ]);
            } else {
                // Avoid duplicate phone collision if another admin already has the same phone
                if (DB::table('admins')->where('phone', $cleanPhone)->exists()) {
                    $cleanPhone = substr($cleanPhone, 0, 15) . '-' . ($idx + 1);
                }
                DB::table('admins')->insert([
                    'f_name' => $fName,
                    'l_name' => $lName,
                    'phone' => $cleanPhone,
                    'email' => $email,
                    'password' => $defaultPasswordHash,
                    'role_id' => $roleId,
                    'marketer_region' => $region,
                    'marketer_code' => $code,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admins')) {
            Schema::table('admins', function (Blueprint $table) {
                if (Schema::hasColumn('admins', 'onboarding_bounty_ghs')) {
                    $table->dropColumn('onboarding_bounty_ghs');
                }
                if (Schema::hasColumn('admins', 'marketer_region')) {
                    $table->dropColumn('marketer_region');
                }
            });
        }
    }

    public static function marketerDataset(): array
    {
        return [
            ['Ansomaa Dankyira', '0555637135', 'ansomaadankyira716@gmail.com', 'Eastern Region'],
            ['DAVID NTI', '0241989011', 'dntim2016@gmail.com', 'Greater Accra Region'],
            ['Agbeve Eli', '0546258274', 'eliagbeve123@gmail.com', 'Volta Region'],
            ['Pinamang Helina', '', 'helinapinamang008@gmail.com', 'Ashanti Region'],
            ['Musah Abdul-Rahim', '0555589769', 'www.musahr240@gmail.com', 'North East Region'],
            ['Francis Komla Broni', '0248078819', 'francisbroni800@gmail.com', 'Volta Region'],
            ['Kun Amuzu', '0561640405', 'kunamuzu@gmail.com', 'Greater Accra Region'],
            ['Leticia Hammond', '0278774518', 'hammondleticia481@gmail.com', 'Greater Accra Region'],
            ['Richmond Agbanyaklu', '0242109736', 'glavic8310.ra@gmail.com', 'Western Region'],
            ['Desmond Opoku Ware', '0267212166', 'desmondopokuware3@gmail.com', 'Ashanti Region'],
            ['Desmond Rockson', '0243707952', 'desmondrockson48@gmail.com', 'Greater Accra Region'],
            ['Abudu Borsutie Hagbentie', '0500120556', 'abuduborsutiehagbentie@gmail.com', 'Upper West Region'],
            ['Daniel kodjo Acolatse', '0550069911', 'gentlemoonenterprise@gmail.com', 'Eastern Region'],
            ['Eugene Nartey Kwei', '0244843034', 'enkwei@googlemail.com', 'Greater Accra Region'],
            ['Apraku Agyei Bernard', '0536689078', 'agyeibernard45@gmail.com', 'Greater Accra Region'],
            ['Israel Sowu Kloku', '0557232510', 'primebrainzino2019@gmail.com', 'Central Region'],
            ['Emmanuel Oppong', '0591119024', 'yawblameless499@gmail.com', 'Ashanti Region'],
            ['Atampugbire Diana Abaasumsona', '0541420602', 'atampugbired@gmail.com', 'Upper East Region'],
            ['Abigail Frempomaa', '0202353258', 'abigailfrimps10@gmail.com', 'Greater Accra Region'],
            ['Kwame Kumi-Acheaw', '233540307282', 'healthylivingfarms1@gmail.com', 'Greater Accra Region'],
            ['Christopher Kojo Kutsoati', '0242063603', 'chriskut2015@gmail.com', 'Ashanti Region'],
            ['Michael Yeboah', '0541667938', 'my8947446@gmail.com', 'Greater Accra Region'],
            ['Agbenyo De-love Francis', '0509536264', 'delove42@gmail.com', 'Greater Accra Region'],
            ['Isaac Ishmael Acquah', '0591461741', 'ishmaelacquah82@gmail.com', 'Central Region'],
            ['Kru Bismark', '0249278246', 'bismarkkru021@gmail.com', 'Bono Region'],
            ['Sunday Yirmeah Zanleriba', '0249612439', 'zanleriba1@gmail.com', 'Greater Accra Region'],
            ['Joshua Ahiave', '0544713250', 'info@promarketa.com', 'Volta Region'],
            ['Akosua Duah', '0243374973', 'akosuaduah18@gmail.com', 'Greater Accra Region'],
            ['Keren Quaye', '0200802557', 'kerhapuch@gmail.com', 'Central Region'],
            ['Anakwah Charles', '0540653313', 'charlesanakwah01@gmail.com', 'Eastern Region'],
            ['Alexander wotor', '0243871722', 'alexanderwotor@gmail.com', 'Greater Accra Region'],
            ['Andrews Owusu Sekyere', '0554053996', 'owusuandrews206@gmail.com', 'Greater Accra Region'],
            ['Richard Kofi Anim Darko', '0559357450', 'aniradvt@gmail.com', 'Greater Accra Region'],
            ['Austin Ntedwah Ampong', '0248123868', 'austinampong7@gmail.com', 'Ashanti Region'],
            ['SALIS DOWONO', '0243831799', 'sdowono7@gmail.com', 'Upper West Region'],
            ['Hayford Agyei Boadi', '0249438566', 'geebeeko12@gmail.com', 'Ashanti Region'],
            ['Anass George', '0246161812', 'georgebows@gmail.com', 'Savannah Region'],
            ['Mala Mapari Emmanuel', '0242683661', 'malanemmanuel94@gmail.com', 'Northern Region'],
            ['Prince Gyan Boampong', '0248111147', 'pgboampong93@gmail.com', 'Eastern Region'],
            ['Bananwmine Haadi', '0553908353', 'banawmine@gmail.com', 'Greater Accra Region'],
            ['SAMUEL DAPAAH', '0243337986', 'dskingdaps@gmail.com', 'Eastern Region'],
            ['Michael Diwan', '0550373055', 'diwanmichael09@gmail.com', 'Oti Region'],
            ['Agyei Bismark', '0249395060', 'agyeibismark806@gmail.com', 'Eastern Region'],
            ['Boateng Jacob Opoku', '0247210469', 'boatengjacob190@gmail.com', 'Western North Region'],
            ['Musah Muktada', '0544735700', 'musahmuktada02@gmail.com', 'Northern Region'],
            ['Vincent Owusu Obeng', '+233240525087', 'obengvincent@gmail.com', 'Eastern Region'],
            ['Reuben Winitim Ali', '0541200310', 'cliqreuben@gmail.com', 'Northern Region'],
            ['Adenyina Evans Achana', '0531118062', 'sarahsandow568@gmail.com', 'Upper East Region'],
            ['ISMAIL RUHULLAHI', '0249895737', 'ismailruhullahi@gmail.com', 'Northern Region'],
            ['Basigi Rosemond', '0241088525', 'basigirose@gmail.com', 'Greater Accra Region'],
            ['Mathew Nang', '0549867797', 'mathewnang@gmail.com', 'Greater Accra Region'],
            ['Muda Mohamed soulemana', '0249444725', 'adamsmuda648@gmail.com', 'Greater Accra Region'],
            ['Bright Baffour Oduro', '0256333390', 'brightkwadwo80@gmail.com', 'Central Region'],
            ['MOHAMMED SHAHID', '0542505520', 'shahid89sessay@gmail.com', 'Upper West Region'],
            ['Enoch Boafo', '0540951492', 'boafoenoch2020@gmail.com', 'Greater Accra Region'],
            ['Ogunwale Sodiq', '+2348067865278', 'soword1@gmail.com', 'Northern Region'],
            ['GOODNEWS KELECHI', '08103357269', 'goodnewskelechukwu@gmail.com', 'Eastern Region'],
            ['Anang Jonas Nii Tettey-Fio', '0241307551', 'niifio06@gmail.com', 'Greater Accra Region'],
            ['AYODELE MOBOLAJI OLAWEPO', '+2348036292701', 'ayodele.mobolaji.olawepo@gmail.com', 'Central Region'],
            ['Okyere Emmanuel Amponsah', '0256748243', 'okyereemmanuel743@gmail.com', 'Eastern Region'],
            ['KWOGYE WETAAM BARNABAS', '0240391265', 'kwo.barnabas@gmail.com', 'Upper East Region'],
            ['Edward Mensah', '0540761597', 'em2337960@gmail.com', 'Eastern Region'],
            ['Adetoro Abdulafeez A', '08163530079', 'abdulafeezadetoro03@gmail.com', 'Western Region'],
            ['Marfo Joseph', '0593858752', 'jomarfo143@gmail.com', 'Ashanti Region'],
            ['Mercy', '050 237 0373', 'blessygh@gmail.com', 'Greater Accra Region'],
            ['Baffour Oduro Bright', '0550142374', 'brightkwadwo80@gmail.com', 'Central Region'],
            ['Edwin Opoku Mensah', '0276088298', 'nkosei96@gmail.com', 'Central Region'],
            ['Jesse Adjetey', '0209879922', 'jesse.a.adjetey@gmail.com', 'Greater Accra Region'],
            ['Sylvester Elolo Apisawu', '0503763133', 'sylvesterelolo@gmail.com', 'Oti Region'],
            ['Aborborgo Evans', '0245864480', 'aborbisanto@gmail.com', 'Volta Region'],
            ['Vitus Naangmenkpieo Salia', '0502415637', 'naagmenkpieo3@gmail.com', 'Upper West Region'],
            ['Kojo Baah', '0243206975', 'resultsghana@gmail.com', 'Central Region'],
            ['Kobina Nyarko Forson', '0540892347', 'nanannyarko971@gmail.com', 'Central Region'],
            ['Saka Habeeb Olasunkanmi', '08143243279', 'sakahabeeb2017@gmail.com', 'Northern Region'],
            ['Charleskolawole', '09024615595', 'charleskolawole806@gmail.com', 'Eastern Region'],
            ['Ganiyu jamiu Akinwale', '09166304760', 'ganiyujamiuakinwale@gmail.com', 'Western Region'],
            ['Emeka Anike Paul', '08038085317', 'ampemyink@gmail.com', 'Western Region'],
            ['Ali Yussif', '0546400010', 'aliyussif596@gmail.com', 'Upper West Region'],
            ['Zakaria Yussif', '0551681152', 'zakariayussif325@gmail.com', 'Northern Region'],
            ['Martin Nuku Kodjo Adjabeng', '0203220419', 'adjabengmartin@gmail.com', 'Volta Region'],
            ['Oniru toheeb abiodun', '08084981685', 'onirutemilade@gmail.com', 'Western Region'],
            ['Nii Antiaye Addy', '0501099530', 'niiantiayea@gmail.com', 'Greater Accra Region'],
            ['Appiah Isaac', '0543844042', 'alphabongo24@gmail.com', 'Central Region'],
            ['Janet Ahadji', '0504049999', 'janetahadji783@gmail.com', 'Volta Region'],
            ['Gloria Sitsofe Ziwu', '0209115431', 'sitsofealorse@gmail.com', 'Volta Region'],
            ['Ogunyebi Matthew taiwo', '09026688909', 'matthewogunyebi26@gmail.com', 'Western Region'],
            ['Gideon Owusu', '0207873515', 'owusugideon04@gmail.com', 'Bono Region'],
            ['Michael Oluwole Adebayo', '15, Third Avenue Emiloju Area Sawmill Papalanto', 'ffiteam378@gmail.com', 'Western Region'],
            ['Jerry Franklin Akonnor', '0244889335', 'jerryakonnor2017@gmail.com', 'Ashanti Region'],
            ['Michael Kumaza', '+233264588944', 'mckuza2@gmail.com', 'Greater Accra Region'],
            ['Enoch Jones', '0595519199', 'fmrlencer@gmail.com', 'Volta Region'],
            ['Samuel Opoku', '0599873618', 'khofisammie@gmail.com', 'Greater Accra Region'],
            ['Sandra mpomaa amoako', '0543060503', 'solacesandy555@gmail.com', 'Greater Accra Region'],
            ['Aikins Amoasi', '0543586294', 'rhabiempie@gmail.com', 'Central Region'],
            ['Ezekiel Agala', '+2347011868316', 'ezekielagala@gmail.com', 'Eastern Region'],
            ['Emmanuel Titilade', '0553887160', 'manadez7@gmail.com', 'Greater Accra Region'],
            ['Kunlangpuuri Abel', '0249087721', 'abelk1997@gmail.com', 'Upper West Region'],
            ['Onoh Promise Onyebuchi', '08066910266', 'onyebuchipromise115@gmail.com', 'Eastern Region'],
            ['Sampson Eshun', '0246537305', 'eshunsampson8@gmail.com', 'Central Region'],
            ['Zakaria Sualey', '0545440180', 'kundasalima190@gmail.com', 'Northern Region'],
            ['Keren Quaye', '0541837397/020082557', 'kerhapuch@gmail.com', 'Central Region'],
            ['Zainab Ibrahim', '+233538344644', 'izainab959@gmail.com', 'Bono Region'],
            ['Daniel Ackah', '233542177601', 'ackahdaniel21@gmail.com', 'Greater Accra Region'],
            ['Apatey Wisdom', '0555175429', 'ahiablemawuko40@gmail.com', 'Volta Region'],
            ['Fred Obed Asamoah', '0207806322', 'asamoahobed361@gmail.com', 'Eastern Region'],
            ['Gideon A. Morrison', '0265339043', 'mhorix56@gmail.com', 'Eastern Region'],
            ['Ibrahim', '0593735278', 'im7405106@gmail.com', 'Western Region'],
            ['Agyapong Sandy Somuah', '0244144677', 'phadaice@gmail.com', 'Ashanti Region'],
            ['Godson Kar Adevu', '0244166459', 'kardeva777@gmail.com', 'Volta Region'],
            ['Olowole Victoria', '09046967857', 'olowolefeyikemi392@gmail.com', 'Western Region'],
            ['Joseph Boluwatife', '07048040248', 'tifegold07@gmail.com', 'Western Region'],
            ['Adegorioye-Adejubaje Fadekemi Pamilerin', '09010775946', 'omolaraadegorioye@gmail.com', 'Western Region'],
            ['Sodiq Mariam', '07025721768', 'mariamsally2@gmail.com', 'Western Region'],
            ['Oluoni Adetolani Cynthia', '07012194913', 'oluoniadetolani@gmail.com', 'North East Region'],
            ['Ogbu Charity', '07012969304', 'ogbucharity052@gmail.com', 'Central Region'],
            ['Ogah Sarah obehi', '09029154774', 'ogahsarah95@gmail.com', 'Western Region'],
            ['Oluwadamilola Rofiat', '09077544634', 'oluwagbenle112@gmail.com', 'Western Region'],
            ['Talabi Kehinde Adeola', '08063086265', 'talabikehinde069@gmail.com', 'Western Region'],
            ['Owolabi timilehin ayomide', '07086233173', 'pamilerintimileyin55@gmail.com', 'North East Region'],
            ['Agyapong Sandy Somuah', '0244244677', 'phadaice@gmail.com', 'Ashanti Region'],
            ['FOSTER ASAMOAH', '0550990126', 'fosterasamoah97@gmail.com', 'Greater Accra Region'],
            ['Gmasuln Michael Nkitibi', '0242722931', 'bibanliblinda@gmail.com', 'Northern Region'],
            ['Jonas. Amankwa', '0535986977', 'jonasamankwah1@gmail.com', 'Central Region'],
        ];
    }
};
