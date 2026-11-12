@extends('layouts.admin.app')
@section('title', 'Remote marketers')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1 class="page-header-title mb-3 mr-1">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>Remote marketers</span>
            </h1>
            <div class="d-flex flex-wrap align-items-center mb-3">
                <a href="{{ route('admin.marketer.commissions') }}" class="btn btn--secondary mr-2">Commission ledger</a>
                <a href="{{ route('admin.marketer.payouts') }}" class="btn btn--secondary mr-2">
                    Payouts
                    @if($openPayouts > 0)
                        <span class="badge badge-soft-danger ml-1">{{ $openPayouts }}</span>
                    @endif
                </a>
                <a href="{{ route('admin.marketer.stores') }}" class="btn btn--secondary mr-2">Store attribution</a>
                <form method="post" action="{{ route('admin.marketer.accrue') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn--primary">Run accrual now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="alert alert-soft-info mb-3">
        A marketer earns a share of the platform commission on delivered orders from stores attributed to them.
        Only commission-model stores accrue. A commission becomes requestable
        {{ (int) config('marketer.holding_days', 14) }} days after delivery, and payouts are approved here and sent by
        mobile money manually.
    </div>

    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header py-2 border-0">
                    <h5 class="card-title mb-0">
                        Marketers <span class="badge badge-soft-dark ml-2">{{ $marketers->count() }}</span>
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                        <tr>
                            <th>Marketer</th>
                            <th>Stores</th>
                            <th>On hold</th>
                            <th>Available</th>
                            <th>In payout</th>
                            <th>Paid</th>
                            <th>Rate</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($marketers as $row)
                            @php($m = $row['admin'])
                            @php($s = $row['summary'])
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <div class="font-weight-600">{{ $m->f_name }} {{ $m->l_name }}</div>
                                            <div class="text-muted small">{{ $m->email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $s['stores'] }}</td>
                                <td>
                                    {{ number_format($s['pending'], 2) }} {{ $s['currency'] }}
                                    <div class="text-muted small">{{ $s['pending_count'] }} orders</div>
                                </td>
                                <td>
                                    <span class="font-weight-600">{{ number_format($s['eligible'], 2) }} {{ $s['currency'] }}</span>
                                    <div class="text-muted small">{{ $s['eligible_count'] }} orders</div>
                                </td>
                                <td>
                                    {{ number_format($s['in_payout'], 2) }} {{ $s['currency'] }}
                                    <div class="text-muted small">{{ $s['in_payout_count'] }} orders</div>
                                </td>
                                <td>
                                    {{ number_format($s['paid'], 2) }} {{ $s['currency'] }}
                                    <div class="text-muted small">{{ $s['paid_count'] }} orders</div>
                                </td>
                                <td style="min-width:150px;">
                                    <form method="post" action="{{ route('admin.marketer.rate', $m->id) }}" class="d-flex align-items-center">
                                        @csrf
                                        <input type="number" step="0.001" min="0" max="100" name="marketer_commission_rate"
                                               value="{{ $m->marketer_commission_rate !== null ? rtrim(rtrim(number_format((float) $m->marketer_commission_rate, 3, '.', ''), '0'), '.') : '' }}"
                                               class="form-control form-control-sm mr-2" style="max-width:100px;"
                                               placeholder="{{ rtrim(rtrim(number_format((float) config('marketer.default_rate', 20), 3, '.', ''), '0'), '.') }}">
                                        <span class="text-muted small mr-2">%</span>
                                        <button type="submit" class="btn btn-sm btn--primary">Save</button>
                                    </form>
                                    <div class="text-muted small mt-1">
                                        Blank uses the default of
                                        {{ rtrim(rtrim(number_format((float) config('marketer.default_rate', 20), 3, '.', ''), '0'), '.') }}%.
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-4">
                                    No accounts have the remote-marketer role yet. Create an employee with the
                                    <strong>Remote Marketer</strong> role, then attribute stores to them.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
