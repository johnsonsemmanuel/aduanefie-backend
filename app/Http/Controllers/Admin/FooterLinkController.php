<?php

namespace App\Http\Controllers\Admin;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Controller;
use App\Models\BusinessSetting;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class FooterLinkController extends Controller
{
    public static function defaultLinks(): array
    {
        return [
            'markets' => [
                ['label' => 'B2B Cross-Border Trade', 'href' => '/trade', 'active' => true],
                ['label' => 'Live Farming', 'href' => '/live-farming', 'active' => true],
                ['label' => 'Ghana Agro Index', 'href' => '/market-prices', 'active' => true],
                ['label' => 'Track Order', 'href' => '/track-order', 'active' => true],
            ],
            'partners' => [
                ['label' => 'Register Your Farm', 'href' => '/store-registration', 'active' => true, 'key' => 'restaurant_owner'],
                ['label' => 'Logistics Partner', 'href' => '/deliveryman-registration', 'active' => true, 'key' => 'delivery_man'],
                ['label' => 'About Us', 'href' => '/about-us', 'active' => true],
                ['label' => 'Help & Support', 'href' => '/help-and-support', 'active' => true],
            ],
        ];
    }

    public function index()
    {
        $raw = BusinessSetting::where('key', 'footer_links')->value('value');
        $footerLinks = $raw ? json_decode($raw, true) : self::defaultLinks();
        if (! is_array($footerLinks)) {
            $footerLinks = self::defaultLinks();
        }

        return view('admin-views.business-settings.footer-links', compact('footerLinks'));
    }

    public function update(Request $request)
    {
        $groups = ['markets', 'partners'];
        $payload = [];

        foreach ($groups as $group) {
            $labels = $request->input("{$group}_label", []);
            $hrefs = $request->input("{$group}_href", []);
            $keys = $request->input("{$group}_key", []);
            $actives = $request->input("{$group}_active", []);

            $items = [];
            foreach ($labels as $idx => $label) {
                $cleanLabel = trim((string) $label);
                $cleanHref = trim((string) ($hrefs[$idx] ?? ''));
                if ($cleanLabel === '' || $cleanHref === '') {
                    continue;
                }
                $entry = [
                    'label' => $cleanLabel,
                    'href' => $cleanHref,
                    'active' => isset($actives[$idx]) && (string) $actives[$idx] === '1',
                ];
                if (! empty($keys[$idx])) {
                    $entry['key'] = trim((string) $keys[$idx]);
                }
                $items[] = $entry;
            }
            $payload[$group] = $items;
        }

        Helpers::businessUpdateOrInsert(['key' => 'footer_links'], [
            'value' => json_encode($payload),
        ]);

        Cache::forget('api_v1_config_payload_v1_en');
        Cache::forget('api_v1_config_payload_v1_' . app()->getLocale());

        Toastr::success('Footer links updated successfully.');
        return back();
    }
}
