@extends('layouts.admin.app')
@section('title', 'Marketer commission ledger')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1 class="page-header-title mb-3 mr-1">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>Commission ledger</span>
            </h1>
            <div class="d-flex flex-wrap align-items-center mb-3">
                <a href="{{ route('admin.marketer.index') }}" class="btn btn--secondary mr-2">Marketers</a>
                <a href="{{ route('admin.marketer.payouts') }}" class="btn btn--secondary mr-2">Payouts</a>
                <form method="post" action="{{ route('admin.marketer.accrue') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn--primary">Run accrual now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="get" action="{{ route('admin.marketer.commissions') }}" class="row align-items-end">
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-600">Marketer</label>
                    <select name="marketer_id" class="form-control">
                        <option value="">All marketers</option>
                        @foreach($marketers as $m)
                            <option value="{{ $m->id }}" @selected((string) $marketerId === (string) $m->id)>
                                {{ $m->f_name }} {{ $m->l_name }} ({{ $m->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small font-weight-600">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach(['pending' => 'On hold', 'eligible' => 'Available', 'in_payout' => 'In payout', 'paid' => 'Paid', 'void' => 'Void'] as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-600">Search</label>
                    <input type="search" name="search" value="{{ $search }}" class="form-control"
                           placeholder="Order number, store or marketer">
                </div>
                <div class="col-md-4 mb-2">
                    <button type="submit" class="btn btn--primary">Apply</button>
                    <a href="{{ route('admin.marketer.commissions') }}" class="btn btn--secondary ml-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 border-0">
            <h5 class="card-title mb-0">
                Commissions <span class="badge badge-soft-dark ml-2">{{ $items->total() }}</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>Order</th>
                    <th>Store</th>
                    <th>Marketer</th>
                    <th>Delivered</th>
                    <th>Platform commission</th>
                    <th>Rate</th>
                    <th>Marketer amount</th>
                    <th>Available from</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $c)
                    <tr>
                        <td>
                            #{{ $c->order_id }}
                            @if($c->payout_id)
                                <div class="text-muted small">
                                    <a href="{{ route('admin.marketer.payouts.show', $c->payout_id) }}">Payout #{{ $c->payout_id }}</a>
                                </div>
                            @endif
                        </td>
                        <td>{{ $c->store?->name ?? '—' }}</td>
                        <td>
                            {{ $c->marketer?->f_name }} {{ $c->marketer?->l_name }}
                            <div class="text-muted small">{{ $c->marketer?->email }}</div>
                        </td>
                        <td>{{ $c->order?->delivered ?? '—' }}</td>
                        <td>{{ number_format($c->base_amount, 2) }} {{ $c->currency }}</td>
                        <td>{{ rtrim(rtrim(number_format($c->rate, 3), '0'), '.') }}%</td>
                        <td class="font-weight-600">{{ number_format($c->amount, 2) }} {{ $c->currency }}</td>
                        <td>{{ $c->eligible_at ?? 'on hold' }}</td>
                        <td>
                            @php($badge = [
                                'pending' => 'warning',
                                'eligible' => 'success',
                                'in_payout' => 'info',
                                'paid' => 'success',
                                'void' => 'danger',
                            ][$c->status] ?? 'secondary')
                            <span class="badge badge-soft-{{ $badge }}">{{ str_replace('_', ' ', $c->status) }}</span>
                            @if($c->void_reason)
                                <div class="text-muted small">{{ str_replace('_', ' ', $c->void_reason) }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4">No commissions match this filter.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="page-area">
            {!! $items->links() !!}
        </div>
    </div>
</div>
@endsection
