@extends('marketer.layout')

@section('title', 'Payouts')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'payouts'])
@endsection

@section('content')
    <div class="card">
        <h1>Payouts</h1>
        <p class="muted small" style="margin-top:0;">
            A payout reserves the commissions listed in it. Aduanefie reviews it, then sends the
            money by mobile money and records the transfer reference. You cannot pay yourself,
            and requesting a payout does not move money.
        </p>

        <div class="grid">
            <div class="stat">
                <div class="label">Available to request</div>
                <div class="value">{{ number_format($summary['eligible'], 2) }} {{ $summary['currency'] }}</div>
            </div>
            <div class="stat">
                <div class="label">Awaiting approval</div>
                <div class="value">{{ number_format($summary['in_payout'], 2) }} {{ $summary['currency'] }}</div>
            </div>
            <div class="stat">
                <div class="label">Paid to date</div>
                <div class="value">{{ number_format($summary['paid'], 2) }} {{ $summary['currency'] }}</div>
            </div>
        </div>

        <p style="margin-bottom:0;">
            <a class="btn" href="{{ route('marketer.payouts.request') }}">Request a payout</a>
        </p>
    </div>

    <div class="card">
        <h2>Payout history</h2>
        @if($items->isEmpty())
            <p class="empty">No payouts yet.</p>
        @else
            <table>
                <thead>
                <tr><th>#</th><th>Amount</th><th>Destination</th><th>Status</th><th>Requested</th><th>Paid</th><th>Reference</th></tr>
                </thead>
                <tbody>
                @foreach($items as $payout)
                    <tr>
                        <td>{{ $payout->id }}</td>
                        <td>{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</td>
                        <td>
                            {{ $payout->network }}<br>
                            <span class="small muted">{{ $payout->account_number }} · {{ $payout->country }}</span>
                        </td>
                        <td>
                            <span class="badge {{ $payout->status }}">{{ $payout->status }}</span>
                            @if($payout->rejection_reason)
                                <div class="small muted">{{ $payout->rejection_reason }}</div>
                            @endif
                        </td>
                        <td>{{ $payout->requested_at }}</td>
                        <td>{{ $payout->paid_at ?? '—' }}</td>
                        <td>{{ $payout->reference ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>

            <div class="pager">
                @if($items->previousPageUrl())
                    <a href="{{ $items->previousPageUrl() }}">Previous</a>
                @endif
                <span class="muted small">Page {{ $items->currentPage() }} of {{ $items->lastPage() }}</span>
                @if($items->nextPageUrl())
                    <a href="{{ $items->nextPageUrl() }}">Next</a>
                @endif
            </div>
        @endif
    </div>
@endsection
