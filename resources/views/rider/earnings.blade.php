@extends('rider.layout')

@section('title', 'My earnings')

@section('topbar')
    <div>
        {{ trim(($rider->f_name ?? '') . ' ' . ($rider->l_name ?? '')) ?: 'Rider' }}
        <form method="post" action="{{ route('rider.logout') }}" style="display:inline;margin-left:8px;">
            @csrf
            <button type="submit" class="link">Sign out</button>
        </form>
    </div>
@endsection

@section('content')
    @php($money = fn ($value) => \App\CentralLogics\Helpers::format_currency((float) $value))

    <nav class="tabs">
        <a href="{{ route('rider.dashboard') }}">Orders</a>
        <a href="{{ route('rider.earnings') }}" class="active">Earnings</a>
    </nav>

    <h2 style="margin-top:0;">Summary</h2>

    <div class="grid">
        <div class="stat">
            <div class="label">Total earned</div>
            <div class="value">{{ $money($wallet->total_earning ?? 0) }}</div>
        </div>
        <div class="stat">
            <div class="label">Withdrawn</div>
            <div class="value">{{ $money($wallet->total_withdrawn ?? 0) }}</div>
        </div>
        <div class="stat">
            <div class="label">Pending withdrawal</div>
            <div class="value">{{ $money($wallet->pending_withdraw ?? 0) }}</div>
        </div>
        <div class="stat">
            <div class="label">Cash collected</div>
            <div class="value">{{ $money($wallet->collected_cash ?? 0) }}</div>
        </div>
        <div class="stat">
            <div class="label">Deliveries completed</div>
            <div class="value">{{ $deliveredCount }}</div>
        </div>
    </div>

    @if(!$wallet)
        <div class="alert info" style="margin-top:16px;">
            No earnings wallet has been opened for your account yet. Balances will appear here once
            your first delivery is settled.
        </div>
    @endif

    <h2>Recent earning entries</h2>

    <div class="card">
        @if($entries->isEmpty())
            <p class="empty" style="margin:0;">No earning entries yet.</p>
        @else
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Method</th>
                        <th>Reference</th>
                        <th style="text-align:right;">Amount</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($entries as $entry)
                    <tr>
                        <td>{{ $entry->created_at ? \Carbon\Carbon::parse($entry->created_at)->format('d M Y, H:i') : '—' }}</td>
                        <td>{{ $entry->method ? ucfirst(str_replace('_', ' ', $entry->method)) : '—' }}</td>
                        <td class="small muted">{{ $entry->ref ?: '—' }}</td>
                        <td style="text-align:right;">{{ $money($entry->amount) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="small muted">
        Payouts are processed by the Aduanefie operations team. Request a withdrawal from the rider app.
    </p>
@endsection
