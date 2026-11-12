@extends('layouts.admin.app')
@section('title', 'Marketer payouts')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1 class="page-header-title mb-3 mr-1">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>Marketer payouts</span>
            </h1>
            <div class="d-flex flex-wrap align-items-center mb-3">
                <a href="{{ route('admin.marketer.index') }}" class="btn btn--secondary mr-2">Marketers</a>
                <a href="{{ route('admin.marketer.commissions') }}" class="btn btn--secondary mr-2">Commission ledger</a>
                <form method="post" action="{{ route('admin.marketer.accrue') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn--primary">Run accrual now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="get" action="{{ route('admin.marketer.payouts') }}" class="row align-items-end">
                <div class="col-md-4 mb-2">
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
                <div class="col-md-3 mb-2">
                    <label class="small font-weight-600">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All statuses</option>
                        @foreach(['requested' => 'Awaiting approval', 'approved' => 'Approved, not sent', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $key => $label)
                            <option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-5 mb-2">
                    <button type="submit" class="btn btn--primary">Apply</button>
                    <a href="{{ route('admin.marketer.payouts') }}" class="btn btn--secondary ml-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 border-0">
            <h5 class="card-title mb-0">
                Payout requests <span class="badge badge-soft-dark ml-2">{{ $items->total() }}</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>#</th>
                    <th>Marketer</th>
                    <th>Amount</th>
                    <th>Destination</th>
                    <th>Status</th>
                    <th>Requested</th>
                    <th>Paid</th>
                    <th>Reference</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                @forelse($items as $payout)
                    <tr>
                        <td>{{ $payout->id }}</td>
                        <td>
                            {{ $payout->marketer?->f_name }} {{ $payout->marketer?->l_name }}
                            <div class="text-muted small">{{ $payout->marketer?->email }}</div>
                        </td>
                        <td class="font-weight-600">{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</td>
                        <td>
                            {{ $payout->network }}
                            <div class="text-muted small">{{ $payout->account_name }} · {{ $payout->account_number }} · {{ $payout->country }}</div>
                        </td>
                        <td>
                            @php($badge = [
                                'requested' => 'warning',
                                'approved' => 'info',
                                'paid' => 'success',
                                'rejected' => 'danger',
                            ][$payout->status] ?? 'secondary')
                            <span class="badge badge-soft-{{ $badge }}">{{ $payout->status }}</span>
                        </td>
                        <td>{{ $payout->requested_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $payout->paid_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>{{ $payout->reference ?? '—' }}</td>
                        <td class="text-right">
                            <a href="{{ route('admin.marketer.payouts.show', $payout->id) }}" class="btn btn-sm btn--secondary">Open</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="text-center py-4">No payouts match this filter.</td>
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
