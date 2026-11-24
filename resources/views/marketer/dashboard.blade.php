@extends('marketer.layout')

@section('title', 'Marketer dashboard')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}
    @if(!empty($marketer->marketer_region))
        <span class="badge">{{ $marketer->marketer_region }}</span>
    @endif
    <br>
    <span class="small">{{ $marketer->email }}</span>
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'dashboard'])
@endsection

@section('content')
    @php
        $refCode = $marketer->marketer_code ?? ('MKT-' . $marketer->id);
        $refUrl = 'https://aduanefie.com/store-registration?ref=' . urlencode($refCode);
        $waText = rawurlencode("Sell your farm produce on Aduanefie Marketplace. Register with code {$refCode}: {$refUrl}");
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($refUrl);
        $bountyRate = number_format($summary['onboarding_bounty_rate'] ?? 25, 2);
        $commRate = rtrim(rtrim(number_format($marketer->marketerCommissionRate(), 3), '0'), '.');
        $kpiItems = [
            [
                'label' => 'Farmers Onboarded',
                'value' => $summary['stores'],
                'meta' => ($summary['verified_stores'] ?? 0) . ' verified · ' . ((int) $summary['stores'] - (int) ($summary['verified_stores'] ?? 0)) . ' pending',
            ],
            [
                'label' => 'Onboarding Bounty',
                'value' => number_format($summary['onboarding_bounty_earned'] ?? 0, 2) . ' ' . $summary['currency'],
                'meta' => $bountyRate . ' ' . $summary['currency'] . ' per verified farmer',
            ],
            [
                'label' => 'Available to Withdraw',
                'value' => number_format($summary['eligible'], 2) . ' ' . $summary['currency'],
                'meta' => number_format($summary['pending'], 2) . ' ' . $summary['currency'] . ' on hold',
            ],
            [
                'label' => 'Paid to Date',
                'value' => number_format($summary['paid'], 2) . ' ' . $summary['currency'],
                'meta' => $summary['paid_count'] . ' orders · ' . $commRate . '% commission rate',
            ],
        ];
    @endphp

    @include('partials.dashboard._page-header', [
        'title' => 'Overview',
        'subtitle' => 'Referral Code: ' . $refCode,
        'actionsHtml' => '<a href="' . route('marketer.register-farmer') . '" class="btn">Register Farmer</a> <a href="' . route('marketer.payouts.request') . '" class="btn secondary">Request Payout</a>',
    ])

    @include('partials.dashboard._kpi-grid', ['items' => $kpiItems])

    <div class="card">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
            <div class="d-flex align-items-center" style="gap:12px;flex:1;min-width:260px;">
                <img src="{{ $qrUrl }}" alt="QR code for {{ $refCode }}" width="72" height="72" style="border:1px solid #E7EAF3;border-radius:8px;padding:4px;background:#fff;flex-shrink:0;">
                <div style="flex:1;">
                    <label for="marketer-ref-url" class="mb-1">Farmer Referral Link ({{ $refCode }})</label>
                    <input type="text" id="marketer-ref-url" value="{{ $refUrl }}" readonly style="background:#F8FAFD;">
                </div>
            </div>
            <div class="d-flex flex-wrap" style="gap:8px;">
                <button type="button" class="btn" onclick="var i=document.getElementById('marketer-ref-url');i.select();navigator.clipboard.writeText(i.value);this.innerText='Copied';setTimeout(()=>this.innerText='Copy Link',2000);">Copy Link</button>
                <a href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener" class="btn secondary">WhatsApp</a>
                <button type="button" onclick="window.print()" class="btn secondary">Print QR</button>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Attributed Farms</h2>
        @if($stores->isEmpty())
            <p class="empty">No farms assigned yet.</p>
        @else
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr>
                        <th>Farmer / Store</th>
                        <th>Type &amp; Region</th>
                        <th>Status</th>
                        <th>Sales</th>
                        <th>Commission</th>
                        <th>Verification</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($stores as $store)
                        <tr>
                            <td>
                                <strong>{{ $store->name }}</strong>
                                @if(!empty($store->phone))
                                    <div class="small muted">{{ $store->phone }}</div>
                                @endif
                            </td>
                            <td>
                                {{ ($store->is_backyard ?? false) ? 'Backyard Grower' : ucfirst($store->farm_type ?? $store->store_business_model) }}
                                @if(!empty($store->region))
                                    <div class="small muted">{{ $store->region }}{{ !empty($store->district) ? ' · ' . $store->district : '' }}</div>
                                @endif
                            </td>
                            <td>{{ ucfirst(str_replace('_', ' ', $store->cultivation_status ?? 'active')) }}</td>
                            <td>
                                <strong>{{ number_format($store->total_sales ?? 0, 2) }} {{ $summary['currency'] }}</strong>
                                <div class="small muted">{{ $store->delivered_orders_count ?? 0 }} orders</div>
                            </td>
                            <td>
                                <strong>{{ number_format($store->commission_earned ?? 0, 2) }} {{ $summary['currency'] }}</strong>
                                <div class="small muted">+ {{ $bountyRate }} {{ $summary['currency'] }} bounty</div>
                            </td>
                            <td>
                                @if($store->marketer_verified ?? false)
                                    <span class="badge paid">Verified</span>
                                @else
                                    <form method="post" action="{{ route('marketer.stores.verify', $store->id) }}" style="display:inline-flex; gap:6px; align-items:center;">
                                        @csrf
                                        <select name="cultivation_status" style="width:auto;padding:4px 8px;font-size:12px;height:32px;">
                                            <option value="farming">Farming</option>
                                            <option value="harvest_ready">Harvest ready</option>
                                            <option value="active">Active</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm">Verify</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="mb-0">Recent Commissions</h2>
                    @if(!$recent->isEmpty())
                        <a href="{{ route('marketer.commissions') }}" class="small">View all</a>
                    @endif
                </div>
                @if($recent->isEmpty())
                    <p class="empty">No commissions yet.</p>
                @else
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr><th>Order</th><th>Store</th><th>Rate</th><th>Amount</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            @foreach($recent as $commission)
                                <tr>
                                    <td>#{{ $commission->order_id }}</td>
                                    <td>{{ $commission->store?->name }}</td>
                                    <td>{{ rtrim(rtrim(number_format($commission->rate, 3), '0'), '.') }}%</td>
                                    <td>{{ number_format($commission->amount, 2) }} {{ $commission->currency }}</td>
                                    <td><span class="badge {{ $commission->status }}">{{ str_replace('_', ' ', $commission->status) }}</span></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="col-lg-5">
            <div class="card">
                <h2>Payouts</h2>
                @if($payouts->isEmpty())
                    <p class="empty">No payout requests yet.</p>
                @else
                    <div class="table-responsive">
                        <table>
                            <thead>
                            <tr><th>#</th><th>Amount</th><th>Status</th><th>Requested</th></tr>
                            </thead>
                            <tbody>
                            @foreach($payouts as $payout)
                                <tr>
                                    <td>{{ $payout->id }}</td>
                                    <td>{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</td>
                                    <td><span class="badge {{ $payout->status }}">{{ $payout->status }}</span></td>
                                    <td>{{ $payout->requested_at }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="card">
                <h2>Update Password</h2>
                <form method="post" action="{{ route('marketer.password.update') }}">
                    @csrf
                    <div class="row">
                        <div class="col-sm-6 field">
                            <label for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" minlength="8" required autocomplete="new-password">
                        </div>
                        <div class="col-sm-6 field">
                            <label for="new_password_confirmation">Confirm Password</label>
                            <input type="password" name="new_password_confirmation" id="new_password_confirmation" minlength="8" required autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn">Update Password</button>
                </form>
            </div>
        </div>
    </div>
@endsection
