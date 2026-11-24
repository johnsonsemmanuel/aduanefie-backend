@extends('layouts.admin.app')

@section('title', 'Remote Marketer Profile · ' . trim($marketer->f_name . ' ' . $marketer->l_name))

@section('content')
@php
    $refCode = $marketer->marketer_code ?: ('MKT-GH-' . str_pad((string) $marketer->id, 3, '0', STR_PAD_LEFT));
    $refUrl = 'https://aduanefie.com/store-registration?ref=' . urlencode($refCode);
@endphp
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="d-flex align-items-center gap-2">
            <span class="page-header-icon">
                <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
            </span>
            <div>
                <h1 class="page-header-title mb-0">{{ trim($marketer->f_name . ' ' . $marketer->l_name) }}</h1>
                <p class="text-muted mb-0">
                    Remote Marketer #{{ $marketer->id }}
                    @if($marketer->marketer_region) · {{ $marketer->marketer_region }} @endif
                    · <span class="badge badge-soft-info">{{ $refCode }}</span>
                </p>
            </div>
        </div>
        <div class="btn--container">
            <form method="post" action="{{ route('admin.marketer-applications.send-login', $marketer->id) }}" class="mb-0">
                @csrf
                <button type="submit" class="btn btn--primary">
                    <i class="tio-send mr-1"></i> Send Login Link (SMS + Email)
                </button>
            </form>
            <a href="{{ route('admin.marketer-applications.index') }}" class="btn btn--secondary">
                <i class="tio-back-ui mr-1"></i> Back to Marketers
            </a>
        </div>
    </div>

    <div class="row g-2 mb-3">
        <div class="col-sm-6 col-lg-3">
            <div class="order--card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="card-subtitle m-0"><span>Farmers Onboarded</span></h6>
                    <span class="card-title text-3F8CE8">{{ $summary['stores'] ?? 0 }}</span>
                </div>
                <small class="text-muted">{{ $summary['verified_stores'] ?? 0 }} verified live</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="order--card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="card-subtitle m-0"><span>Delivered Sales</span></h6>
                    <span class="card-title text-success">{{ number_format($summary['total_sales'] ?? 0, 2) }} {{ $summary['currency'] }}</span>
                </div>
                <small class="text-muted">{{ $summary['delivered_orders_count'] ?? 0 }} orders</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="order--card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="card-subtitle m-0"><span>Available / Pending</span></h6>
                    <span class="card-title text-warning">{{ number_format($summary['eligible'] ?? 0, 2) }} {{ $summary['currency'] }}</span>
                </div>
                <small class="text-muted">{{ number_format($summary['pending'] ?? 0, 2) }} {{ $summary['currency'] }} on hold</small>
            </div>
        </div>
        <div class="col-sm-6 col-lg-3">
            <div class="order--card h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <h6 class="card-subtitle m-0"><span>Total Paid</span></h6>
                    <span class="card-title text-success">{{ number_format($summary['paid'] ?? 0, 2) }} {{ $summary['currency'] }}</span>
                </div>
                <small class="text-muted">Bounty: {{ number_format($summary['onboarding_bounty_earned'] ?? 0, 2) }} {{ $summary['currency'] }}</small>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Contact &amp; Referral Details</h5>
                </div>
                <div class="card-body">
                    <p class="mb-2"><strong>Email:</strong> {{ $marketer->email }}</p>
                    <p class="mb-2"><strong>Phone:</strong> {{ $marketer->phone ?: '—' }}</p>
                    <p class="mb-2"><strong>Region:</strong> {{ $marketer->marketer_region ?: 'Unassigned' }}</p>
                    <p class="mb-2"><strong>Commission Rate:</strong> {{ rtrim(rtrim(number_format($marketer->marketerCommissionRate(), 3), '0'), '.') }}%</p>
                    <p class="mb-2"><strong>Joined:</strong> {{ optional($marketer->created_at)->format('M d, Y') }}</p>
                    <div class="mt-3">
                        <label class="input-label">Farmer Referral Link</label>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" value="{{ $refUrl }}" readonly id="admin-mkt-ref">
                            <div class="input-group-append">
                                <button type="button" class="btn btn-outline-primary" onclick="navigator.clipboard.writeText(document.getElementById('admin-mkt-ref').value);this.textContent='Copied!';">Copy</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">Attributed Farmers &amp; Stores ({{ $stores->count() }})</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-borderless table-thead-bordered table-align-middle">
                        <thead class="thead-light">
                            <tr>
                                <th>Store / Farm</th>
                                <th>Region</th>
                                <th>Status</th>
                                <th>Orders &amp; Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stores as $store)
                                <tr>
                                    <td>
                                        <strong>{{ $store->name }}</strong><br>
                                        <small class="text-muted">{{ $store->phone }}</small>
                                    </td>
                                    <td>{{ $store->region ?? '—' }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $store->status ? 'success' : 'warning' }}">
                                            {{ $store->status ? 'Verified' : 'Pending' }}
                                        </span>
                                    </td>
                                    <td>
                                        <strong>{{ number_format($store->total_sales ?? 0, 2) }} {{ $summary['currency'] }}</strong><br>
                                        <small class="text-muted">{{ $store->delivered_orders_count ?? 0 }} orders</small>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted py-4">No stores attributed to this marketer yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent Payouts ({{ $payouts->count() }})</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Requested</th>
                        <th>Paid</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payouts as $p)
                        <tr>
                            <td>#{{ $p->id }}</td>
                            <td>{{ number_format($p->amount, 2) }} {{ $p->currency }}</td>
                            <td><span class="badge badge-soft-{{ $p->status === 'paid' ? 'success' : ($p->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($p->status) }}</span></td>
                            <td>{{ $p->requested_at }}</td>
                            <td>{{ $p->paid_at ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No payout requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
