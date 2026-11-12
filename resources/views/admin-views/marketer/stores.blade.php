@extends('layouts.admin.app')
@section('title', 'Store attribution')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <div class="d-flex flex-wrap align-items-center justify-content-between">
            <h1 class="page-header-title mb-3 mr-1">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/role.png') }}" class="w--26" alt="">
                </span>
                <span>Store attribution</span>
            </h1>
            <div class="d-flex flex-wrap align-items-center mb-3">
                <a href="{{ route('admin.marketer.index') }}" class="btn btn--secondary mr-2">Marketers</a>
                <a href="{{ route('admin.marketer.commissions') }}" class="btn btn--secondary mr-2">Commission ledger</a>
                <a href="{{ route('admin.marketer.payouts') }}" class="btn btn--secondary mr-2">Payouts</a>
                <form method="post" action="{{ route('admin.marketer.accrue') }}" class="mb-0">
                    @csrf
                    <button type="submit" class="btn btn--primary">Run accrual now</button>
                </form>
            </div>
        </div>
    </div>

    <div class="alert alert-soft-info mb-3">
        Commission accrues only for <strong>commission-model</strong> stores, and only for orders delivered
        <strong>after</strong> the marketer is attached. Attaching a marketer to an established store does not pay for
        its past orders. Switching marketers starts the new one from today.
    </div>

    <div class="card mb-3">
        <div class="card-body py-3">
            <form method="get" action="{{ route('admin.marketer.stores') }}" class="row align-items-end">
                <div class="col-md-4 mb-2">
                    <label class="small font-weight-600">Search</label>
                    <input type="search" name="search" value="{{ $search }}" class="form-control"
                           placeholder="Store name or phone">
                </div>
                <div class="col-md-4 mb-2">
                    <label class="small font-weight-600">Marketer</label>
                    <select name="marketer_id" class="form-control">
                        <option value="">All stores</option>
                        <option value="none" @selected($marketerId === 'none')>Not attributed</option>
                        @foreach($marketers as $m)
                            <option value="{{ $m->id }}" @selected((string) $marketerId === (string) $m->id)>
                                {{ $m->f_name }} {{ $m->l_name }} ({{ $m->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <button type="submit" class="btn btn--primary">Apply</button>
                    <a href="{{ route('admin.marketer.stores') }}" class="btn btn--secondary ml-2">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header py-2 border-0">
            <h5 class="card-title mb-0">
                Stores <span class="badge badge-soft-dark ml-2">{{ $stores->total() }}</span>
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                <thead class="thead-light">
                <tr>
                    <th>Store</th>
                    <th>Model</th>
                    <th>Current marketer</th>
                    <th>Attributed since</th>
                    <th style="min-width:320px;">Assign</th>
                </tr>
                </thead>
                <tbody>
                @forelse($stores as $store)
                    <tr>
                        <td>
                            <div class="font-weight-600">{{ $store->name }}</div>
                            <div class="text-muted small">{{ $store->phone }}</div>
                        </td>
                        <td>
                            @php($modelBadge = [
                                'commission' => 'success',
                                'subscription' => 'info',
                                'unsubscribed' => 'danger',
                                'none' => 'secondary',
                            ][$store->store_business_model] ?? 'secondary')
                            <span class="badge badge-soft-{{ $modelBadge }}">{{ $store->store_business_model }}</span>
                            @if($store->store_business_model !== 'commission')
                                <div class="text-muted small">Does not accrue commission</div>
                            @endif
                        </td>
                        <td>
                            @if($store->marketer)
                                <div class="font-weight-600">{{ $store->marketer->f_name }} {{ $store->marketer->l_name }}</div>
                                <div class="text-muted small">{{ $store->marketer->email }}</div>
                            @else
                                <span class="text-muted">Not attributed</span>
                            @endif
                        </td>
                        <td>
                            @if($store->marketer_assigned_at)
                                {{ \Illuminate\Support\Carbon::parse($store->marketer_assigned_at)->format('Y-m-d H:i') }}
                                <div class="text-muted small">Earlier deliveries are excluded</div>
                            @else
                                —
                            @endif
                        </td>
                        <td>
                            <form method="post" action="{{ route('admin.marketer.stores.assign', $store->id) }}" class="d-flex align-items-center">
                                @csrf
                                <select name="marketer_id" class="form-control form-control-sm mr-2">
                                    <option value="">— none —</option>
                                    @foreach($marketers as $m)
                                        <option value="{{ $m->id }}" @selected((int) $store->marketer_id === (int) $m->id)>
                                            {{ $m->f_name }} {{ $m->l_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <button type="submit" class="btn btn-sm btn--primary">Save</button>
                            </form>
                            @if($store->marketer)
                                <div class="text-muted small mt-1">
                                    Choosing “none” removes the marketer and stops future accrual.
                                </div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-4">No stores match this filter.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="page-area">
            {!! $stores->links() !!}
        </div>
    </div>
</div>
@endsection
