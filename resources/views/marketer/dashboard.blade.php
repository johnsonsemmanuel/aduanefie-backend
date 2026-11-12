@extends('marketer.layout')

@section('title', 'Marketer dashboard')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}<br>
    <span class="small">{{ $marketer->email }}</span>
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'dashboard'])
@endsection

@section('content')
    <div class="card">
        <h1>Your stores</h1>
        <p class="muted small" style="margin-top:0;">
            You earn {{ rtrim(rtrim(number_format($marketer->marketerCommissionRate(), 3), '0'), '.') }}%
            of Aduanefie's commission on each delivered order from these stores.
            A commission can be requested {{ (int) config('marketer.holding_days', 14) }} days after delivery.
        </p>

        <div class="grid">
            <div class="stat">
                <div class="label">Stores</div>
                <div class="value">{{ $summary['stores'] }}</div>
            </div>
            <div class="stat">
                <div class="label">On hold</div>
                <div class="value">{{ number_format($summary['pending'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['pending_count'] }} orders</div>
            </div>
            <div class="stat">
                <div class="label">Available</div>
                <div class="value">{{ number_format($summary['eligible'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['eligible_count'] }} orders</div>
            </div>
            <div class="stat">
                <div class="label">Requested / in payout</div>
                <div class="value">{{ number_format($summary['in_payout'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['in_payout_count'] }} orders</div>
            </div>
            <div class="stat">
                <div class="label">Paid to date</div>
                <div class="value">{{ number_format($summary['paid'], 2) }} {{ $summary['currency'] }}</div>
                <div class="small muted">{{ $summary['paid_count'] }} orders</div>
            </div>
        </div>

        @if($summary['next_eligible_at'])
            <p class="small muted" style="margin-bottom:0;">
                Next commission becomes available on {{ $summary['next_eligible_at'] }}.
            </p>
        @endif
        <p class="small muted" style="margin-bottom:0;">
            Your farmer referral code: <strong>{{ $marketer->marketer_code ?? ('MKT-' . $marketer->id) }}</strong>
            — share this when onboarding commercial farms or backyard growers.
        </p>
    </div>

    <div class="card">
        <h2>Stores credited to you</h2>
        @if($stores->isEmpty())
            <p class="empty">No stores have been assigned to you yet.</p>
        @else
            <table>
                <thead>
                <tr><th>Store</th><th>Type</th><th>Cultivation</th><th>Status</th><th>Field verification</th></tr>
                </thead>
                <tbody>
                @foreach($stores as $store)
                    <tr>
                        <td>{{ $store->name }}</td>
                        <td>{{ ($store->is_backyard ?? false) ? 'Backyard Grower' : ucfirst($store->farm_type ?? $store->store_business_model) }}</td>
                        <td>{{ ucfirst(str_replace('_', ' ', $store->cultivation_status ?? 'active')) }}</td>
                        <td>{{ $store->status ? 'live' : 'not live' }}</td>
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
                        <td>{{ $payout->paid_at ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
        <p style="margin-bottom:0;">
            <a class="btn" href="{{ route('marketer.payouts.request') }}">Request a payout</a>
        </p>
    </div>
@endsection
