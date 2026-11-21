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
    <div class="card">
        <h1>Your Earnings &amp; Farmer Onboarding</h1>
        <p class="muted small" style="margin-top:0;">
            You earn <strong>{{ number_format($summary['onboarding_bounty_rate'] ?? 25, 2) }} {{ $summary['currency'] }}</strong> for every verified farmer / agro-stakeholder you bring onboard, plus
            <strong>{{ rtrim(rtrim(number_format($marketer->marketerCommissionRate(), 3), '0'), '.') }}%</strong>
            of Aduanefie's commission on every delivered order from your attributed farms.
            Sales commissions become requestable {{ (int) config('marketer.holding_days', 14) }} days after delivery.
        </p>

        <div class="grid">
            <div class="stat">
                <div class="label">Farmers Onboarded</div>
                <div class="value">{{ $summary['stores'] }}</div>
                <div class="small muted">{{ $summary['verified_stores'] ?? 0 }} verified live · {{ (int) $summary['stores'] - (int) ($summary['verified_stores'] ?? 0) }} awaiting verification</div>
            </div>
            <div class="stat">
                <div class="label">Onboarding Bounty</div>
                <div class="value">{{ number_format($summary['onboarding_bounty_earned'] ?? 0, 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">@ {{ number_format($summary['onboarding_bounty_rate'] ?? 25, 2) }} {{ $summary['currency'] }} / farmer · {{ number_format(((int) $summary['stores'] - (int) ($summary['verified_stores'] ?? 0)) * (float) ($summary['onboarding_bounty_rate'] ?? 25), 2) }} {{ $summary['currency'] }} pending verification</div>
            </div>
            <div class="stat">
                <div class="label">Total Farmer Sales</div>
                <div class="value">{{ number_format($summary['total_sales'] ?? 0, 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['delivered_orders_count'] ?? 0 }} delivered orders</div>
            </div>
            <div class="stat">
                <div class="label">Commission On Hold</div>
                <div class="value">{{ number_format($summary['pending'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['pending_count'] }} orders</div>
            </div>
            <div class="stat">
                <div class="label">Available to Withdraw</div>
                <div class="value">{{ number_format($summary['eligible'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['eligible_count'] }} orders</div>
            </div>
            <div class="stat">
                <div class="label">Paid to Date</div>
                <div class="value">{{ number_format($summary['paid'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['paid_count'] }} orders</div>
            </div>
        </div>

        @if($summary['next_eligible_at'])
            <p class="small muted" style="margin-bottom:0;">
                Next commission becomes available on {{ $summary['next_eligible_at'] }}.
            </p>
        @endif
        <p class="small muted" style="margin-bottom:8px;">
            Your farmer referral code: <strong>{{ $marketer->marketer_code ?? ('MKT-' . $marketer->id) }}</strong>
            - share this when onboarding commercial farms or backyard growers.
        </p>
        @php
            $refCode = $marketer->marketer_code ?? ('MKT-' . $marketer->id);
            $refUrl = 'https://aduanefie.com/store-registration?ref=' . urlencode($refCode);
            $waText = rawurlencode("Sell your farm produce directly on Aduanefie Marketplace! Register using my Regional Marketer link ({$refCode}): {$refUrl}");
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($refUrl);
        @endphp
        <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin-top:8px;">
            <img src="{{ $qrUrl }}" alt="QR code for {{ $refCode }}" width="96" height="96" style="border:1px solid #E6DCC4;border-radius:8px;padding:4px;background:#fff;flex-shrink:0;">
            <div style="flex:1;min-width:240px;">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                    <input type="text" id="marketer-ref-url" value="{{ $refUrl }}" readonly style="flex:1;min-width:220px;padding:7px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;background:#f9fafb;">
                    <button type="button" onclick="var i=document.getElementById('marketer-ref-url');i.select();navigator.clipboard.writeText(i.value);this.innerText='Copied!';setTimeout(()=>this.innerText='Copy Link',2000);" style="padding:7px 14px;background:#67513A;color:#fff;border:none;border-radius:6px;font-size:13px;cursor:pointer;">Copy Link</button>
                    <a href="https://wa.me/?text={{ $waText }}" target="_blank" rel="noopener" style="padding:7px 14px;background:#25D366;color:#fff;border-radius:6px;font-size:13px;text-decoration:none;font-weight:600;">Share on WhatsApp</a>
                    <a href="{{ route('marketer.register-farmer') }}" class="btn" style="padding:7px 14px;font-size:13px;text-decoration:none;">+ Register Farmer</a>
                    <button type="button" onclick="window.print()" class="btn secondary" style="padding:7px 12px;font-size:13px;">Print QR Poster</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>Stores credited to you</h2>
        @if($stores->isEmpty())
            <p class="empty">No stores have been assigned to you yet.</p>
        @else
            <table>
                <thead>
                <tr><th>Farmer / Store</th><th>Type &amp; Region</th><th>Cultivation</th><th>Orders &amp; Sales</th><th>Your Commission</th><th>Field verification</th></tr>
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
                            <div class="small muted">{{ $store->delivered_orders_count ?? 0 }} delivered orders</div>
                        </td>
                        <td>
                            <strong>{{ number_format($store->commission_earned ?? 0, 2) }} {{ $summary['currency'] }}</strong>
                            <div class="small muted">+ {{ number_format($summary['onboarding_bounty_rate'] ?? 25, 2) }} {{ $summary['currency'] }} bounty</div>
                        </td>
                        <td>
                            @if($store->marketer_verified ?? false)
                                <span class="badge paid">Verified</span>
                            @else
                                <form method="post" action="{{ route('marketer.stores.verify', $store->id) }}" style="display:inline-flex; gap:6px; align-items:center;">
                                    @csrf
                                    <select name="cultivation_status" style="padding:3px 6px; font-size:12px;">
                                        <option value="farming">Farming</option>
                                        <option value="harvest_ready">Harvest ready</option>
                                        <option value="active">Active</option>
                                    </select>
                                    <button type="submit" class="btn" style="padding:4px 8px; font-size:12px;">Verify Farming</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="card">
        <h2>Recent commissions</h2>
        @if($recent->isEmpty())
            <p class="empty">No commissions yet. They appear after an order from one of your stores is delivered.</p>
        @else
            <table>
                <thead>
                <tr><th>Order</th><th>Store</th><th>Platform commission</th><th>Rate</th><th>Yours</th><th>Status</th></tr>
                </thead>
                <tbody>
                @foreach($recent as $commission)
                    <tr>
                        <td>#{{ $commission->order_id }}</td>
                        <td>{{ $commission->store?->name }}</td>
                        <td>{{ number_format($commission->base_amount, 2) }}</td>
                        <td>{{ rtrim(rtrim(number_format($commission->rate, 3), '0'), '.') }}%</td>
                        <td>{{ number_format($commission->amount, 2) }} {{ $commission->currency }}</td>
                        <td><span class="badge {{ $commission->status }}">{{ str_replace('_', ' ', $commission->status) }}</span></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <p class="small" style="margin-bottom:0;"><a href="{{ route('marketer.commissions') }}">See all commissions</a></p>
        @endif
    </div>

    <div class="card">
        <h2>Payouts</h2>
        @if($payouts->isEmpty())
            <p class="empty">No payout requests yet.</p>
        @else
            <table>
                <thead>
                <tr><th>#</th><th>Amount</th><th>Status</th><th>Requested</th><th>Paid</th></tr>
                </thead>
                <tbody>
                @foreach($payouts as $payout)
                    <tr>
                        <td>{{ $payout->id }}</td>
                        <td>{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</td>
                        <td><span class="badge {{ $payout->status }}">{{ $payout->status }}</span></td>
                        <td>{{ $payout->requested_at }}</td>
                        <td>{{ $payout->paid_at ?? '-' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        <p style="margin-bottom:0;">
            <a class="btn" href="{{ route('marketer.payouts.request') }}">Request a payout</a>
        </p>
    </div>

    <div class="card">
        <h2>Set or Update Portal Password</h2>
        <p class="muted small" style="margin-top:0;">
            Keep your account ready for direct sign-in at <strong>admin.aduanefie.com/marketer-login</strong>.
        </p>
        <form method="post" action="{{ route('marketer.password.update') }}" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;">
            @csrf
            <div class="field" style="margin-bottom:0;">
                <label for="new_password">New Password (min 8 chars)</label>
                <input type="password" name="new_password" id="new_password" minlength="8" required autocomplete="new-password">
            </div>
            <div class="field" style="margin-bottom:0;">
                <label for="new_password_confirmation">Confirm Password</label>
                <input type="password" name="new_password_confirmation" id="new_password_confirmation" minlength="8" required autocomplete="new-password">
            </div>
            <div>
                <button type="submit">Update Password</button>
            </div>
        </form>
    </div>
@endsection
