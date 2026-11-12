@extends('marketer.layout')

@section('title', 'Commissions')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'commissions'])
@endsection

@section('content')
    <div class="card">
        <h1>Commissions</h1>
        <p class="muted small" style="margin-top:0;">
            Each row is one delivered order. <strong>On hold</strong> means the
            {{ (int) config('marketer.holding_days', 14) }}-day window after delivery has not finished,
            or a refund is being reviewed. <strong>Void</strong> means the order was canceled or refunded.
        </p>

        <div class="grid" style="margin-bottom:16px;">
            <div class="stat"><div class="label">On hold</div><div class="value">{{ number_format($summary['pending'], 2) }}</div></div>
            <div class="stat"><div class="label">Available</div><div class="value">{{ number_format($summary['eligible'], 2) }}</div></div>
            <div class="stat"><div class="label">In payout</div><div class="value">{{ number_format($summary['in_payout'], 2) }}</div></div>
            <div class="stat"><div class="label">Paid</div><div class="value">{{ number_format($summary['paid'], 2) }}</div></div>
        </div>

        <form method="get" action="{{ route('marketer.commissions') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
            <div class="field" style="margin-bottom:0;min-width:200px;">
                <label for="status">Filter</label>
                <select name="status" id="status">
                    <option value="">All statuses</option>
                    @foreach(['pending' => 'On hold', 'eligible' => 'Available', 'in_payout' => 'In payout', 'paid' => 'Paid', 'void' => 'Void'] as $key => $label)
                        <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit">Apply</button>
        </form>
    </div>

    <div class="card">
        @if($items->isEmpty())
            <p class="empty">No commissions match this filter.</p>
        @else
            <table>
                <thead>
                <tr>
                    <th>Order</th><th>Store</th><th>Delivered</th><th>Platform commission</th>
                    <th>Rate</th><th>Yours</th><th>Available from</th><th>Status</th>
                </tr>
                </thead>
                <tbody>
                @foreach($items as $commission)
                    <tr>
                        <td>#{{ $commission->order_id }}</td>
                        <td>{{ $commission->store?->name }}</td>
                        <td>{{ $commission->order?->delivered ?? '—' }}</td>
                        <td>{{ number_format($commission->base_amount, 2) }}</td>
                        <td>{{ rtrim(rtrim(number_format($commission->rate, 3), '0'), '.') }}%</td>
                        <td>{{ number_format($commission->amount, 2) }} {{ $commission->currency }}</td>
                        <td>{{ $commission->eligible_at ?? 'on hold' }}</td>
                        <td><span class="badge {{ $commission->status }}">{{ str_replace('_', ' ', $commission->status) }}</span></td>
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
