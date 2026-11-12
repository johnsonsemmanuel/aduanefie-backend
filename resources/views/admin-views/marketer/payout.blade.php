@extends('layouts.admin.app')
@section('title', 'Payout #' . $payout->id)

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1 class="page-header-title mb-3 mr-1">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>Payout #{{ $payout->id }}</span>
            </h1>
            <div class="d-flex flex-wrap align-items-center mb-3">
                <a href="{{ route('admin.marketer.payouts') }}" class="btn btn--secondary mr-2">All payouts</a>
                <a href="{{ route('admin.marketer.commissions', ['marketer_id' => $payout->marketer_id]) }}" class="btn btn--secondary mr-2">Marketer ledger</a>
            </div>
        </div>
    </div>

    @php($badge = [
        'requested' => 'warning',
        'approved' => 'info',
        'paid' => 'success',
        'rejected' => 'danger',
    ][$payout->status] ?? 'secondary')

    <div class="row">
        <div class="col-lg-5 mb-3">
            <div class="card h-100">
                <div class="card-header py-2 border-0">
                    <h5 class="card-title mb-0">
                        Request
                        <span class="badge badge-soft-{{ $badge }} ml-2">{{ $payout->status }}</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Amount</span>
                        <span class="font-weight-600">{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Method</span>
                        <span>{{ str_replace('_', ' ', $payout->method) }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Network</span>
                        <span>{{ $payout->network ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Account name</span>
                        <span>{{ $payout->account_name ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Account number</span>
                        <span>{{ $payout->account_number ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Country</span>
                        <span>{{ $payout->country ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Requested</span>
                        <span>{{ $payout->requested_at?->format('Y-m-d H:i') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Approved</span>
                        <span>
                            {{ $payout->approved_at?->format('Y-m-d H:i') ?? '—' }}
                            @if($payout->approver)
                                <div class="text-muted small">by {{ $payout->approver->f_name }} {{ $payout->approver->l_name }}</div>
                            @endif
                        </span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Paid</span>
                        <span>{{ $payout->paid_at?->format('Y-m-d H:i') ?? '—' }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted">Reference</span>
                        <span>{{ $payout->reference ?? '—' }}</span>
                    </div>
                    @if($payout->note)
                        <hr>
                        <div class="text-muted small font-weight-600">Marketer note</div>
                        <div>{{ $payout->note }}</div>
                    @endif
                    @if($payout->rejection_reason)
                        <hr>
                        <div class="text-muted small font-weight-600">Rejection reason</div>
                        <div class="text-danger">{{ $payout->rejection_reason }}</div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7 mb-3">
            <div class="card h-100">
                <div class="card-header py-2 border-0">
                    <h5 class="card-title mb-0">Marketer</h5>
                </div>
                <div class="card-body">
                    <div class="font-weight-600">{{ $payout->marketer?->f_name }} {{ $payout->marketer?->l_name }}</div>
                    <div class="text-muted">{{ $payout->marketer?->email }}</div>
                    @if($payout->marketer?->phone)
                        <div class="text-muted">{{ $payout->marketer->phone }}</div>
                    @endif

                    <hr>

                    @if($payout->status === \App\Models\MarketerPayout::STATUS_REQUESTED)
                        <div class="alert alert-soft-info mb-3">
                            Approving checks the underlying orders are still delivered and unrefunded.
                            <strong>No money moves.</strong> Send the transfer yourself, then record the reference below.
                        </div>
                        <form method="post" action="{{ route('admin.marketer.payouts.approve', $payout->id) }}" class="mb-3">
                            @csrf
                            <button type="submit" class="btn btn--primary">
                                Approve {{ number_format($payout->amount, 2) }} {{ $payout->currency }}
                            </button>
                        </form>
                    @elseif($payout->status === \App\Models\MarketerPayout::STATUS_APPROVED)
                        <div class="alert alert-soft-warning mb-3">
                            Approved but not sent. Pay
                            <strong>{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</strong>
                            to {{ $payout->account_number }} ({{ $payout->network }}) first, then record the transfer
                            reference. The reference is what reconciles this payout with the mobile-money statement.
                        </div>
                        <form method="post" action="{{ route('admin.marketer.payouts.paid', $payout->id) }}" class="mb-3">
                            @csrf
                            <div class="form-group">
                                <label class="small font-weight-600" for="reference">Transfer reference</label>
                                <input type="text" id="reference" name="reference" class="form-control" maxlength="100"
                                       placeholder="e.g. MP240611.1432.A12345" required>
                                <small class="text-muted">Required. There is no way to record a payout as paid without it.</small>
                            </div>
                            <button type="submit" class="btn btn--primary">Record as paid</button>
                        </form>
                    @elseif($payout->status === \App\Models\MarketerPayout::STATUS_PAID)
                        <div class="alert alert-soft-success mb-0">
                            Paid on {{ $payout->paid_at?->format('Y-m-d H:i') }} with reference
                            <strong>{{ $payout->reference }}</strong>. This payout is closed.
                        </div>
                    @else
                        <div class="alert alert-soft-danger mb-0">
                            Rejected. Its commissions were released back to the marketer's available balance.
                        </div>
                    @endif

                    @if(in_array($payout->status, [\App\Models\MarketerPayout::STATUS_REQUESTED, \App\Models\MarketerPayout::STATUS_APPROVED], true))
                        <hr>
                        <form method="post" action="{{ route('admin.marketer.payouts.reject', $payout->id) }}">
                            @csrf
                            <div class="form-group">
                                <label class="small font-weight-600" for="reason">Reject this payout</label>
                                <input type="text" id="reason" name="reason" class="form-control" maxlength="255"
                                       placeholder="Why it is being refused" required>
                                <small class="text-muted">Rejecting releases the reserved commissions so the marketer can request again.</small>
                            </div>
                            <button type="submit" class="btn btn--danger">Reject payout</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 border-0">
            <h5 class="card-title mb-0">
                Commissions in this payout
                <span class="badge badge-soft-dark ml-2">{{ $payout->commissions->count() }}</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>Order</th>
                    <th>Store</th>
                    <th>Delivered</th>
                    <th>Platform commission</th>
                    <th>Rate</th>
                    <th>Marketer amount</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                @forelse($payout->commissions as $c)
                    <tr>
                        <td>#{{ $c->order_id }}</td>
                        <td>{{ $c->store?->name ?? '—' }}</td>
                        <td>{{ $c->order?->delivered ?? '—' }}</td>
                        <td>{{ number_format($c->base_amount, 2) }} {{ $c->currency }}</td>
                        <td>{{ rtrim(rtrim(number_format($c->rate, 3), '0'), '.') }}%</td>
                        <td class="font-weight-600">{{ number_format($c->amount, 2) }} {{ $c->currency }}</td>
                        <td>
                            @php($rowBadge = [
                                'pending' => 'warning',
                                'eligible' => 'success',
                                'in_payout' => 'info',
                                'paid' => 'success',
                                'void' => 'danger',
                            ][$c->status] ?? 'secondary')
                            <span class="badge badge-soft-{{ $rowBadge }}">{{ str_replace('_', ' ', $c->status) }}</span>
                            @if($c->void_reason)
                                <div class="text-muted small">{{ str_replace('_', ' ', $c->void_reason) }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            This payout has no commissions. It was most likely auto-rejected because every order in it
                            was refunded or canceled.
                        </td>
                    </tr>
                @endforelse
                </tbody>
                <tfoot>
                <tr>
                    <th colspan="5" class="text-right">Total</th>
                    <th colspan="2">{{ number_format($payout->amount, 2) }} {{ $payout->currency }}</th>
                </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
