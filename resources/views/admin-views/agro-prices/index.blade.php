@extends('layouts.admin.app')

@section('title', 'Ghana Agro Market Prices')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-chart-bar-4"></i>
                </span>
                <span>Ghana Agro Market Prices &amp; SMS Alerts</span>
            </h1>
            <p class="text-muted mb-0">
                Manage live Ghana wholesale &amp; retail commodity prices shown on the marketplace ticker and broadcast Hubtel SMS price alerts to farmers.
            </p>
        </div>
        <div>
            <a href="https://aduanefie.com/market-prices" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
                <i class="tio-open-in-new"></i> View Live Index
            </a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="tio-add-circle"></i> Add Commodity Price</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.agro-prices.store') }}" method="POST">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="input-label">Commodity</label>
                                <input type="text" name="commodity" class="form-control" placeholder="e.g. Yellow Maize" required>
                            </div>
                            <div class="col-md-4">
                                <label class="input-label">Category</label>
                                <input type="text" name="category" class="form-control" placeholder="e.g. Grains &amp; Cereals" required>
                            </div>
                            <div class="col-md-4">
                                <label class="input-label">Unit</label>
                                <input type="text" name="unit" class="form-control" placeholder="e.g. 100kg Bag" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Market Hub</label>
                                <input type="text" name="market_hub" class="form-control" placeholder="e.g. Techiman" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Wholesale (GHS)</label>
                                <input type="number" step="0.01" min="0" name="wholesale_price_ghs" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Retail (GHS)</label>
                                <input type="number" step="0.01" min="0" name="retail_price_ghs" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Change (%)</label>
                                <input type="number" step="0.01" name="price_change_pct" class="form-control" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Trend</label>
                                <select name="trend" class="form-control">
                                    <option value="stable">Stable</option>
                                    <option value="up">Up</option>
                                    <option value="down">Down</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="input-label">Source Platform</label>
                                <input type="text" name="source_platform" class="form-control" value="Aduanefie Field Desk">
                            </div>
                            <div class="col-md-4 d-flex align-items-end justify-content-between">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="add_is_active" checked>
                                    <label class="form-check-label" for="add_is_active">Active</label>
                                </div>
                                <button type="submit" class="btn btn-primary">Add Price</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="tio-sms"></i> Broadcast Price Alert SMS (Hubtel)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Sends the top 4 active wholesale commodity prices to <strong>{{ $activeStorePhonesCount }}</strong> active farm/store phone(s), or enter a single test number below.
                    </p>
                    <form action="{{ route('admin.agro-prices.send-sms') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <label class="input-label">Test Phone (optional — leave blank to broadcast to all active stores)</label>
                            <input type="text" name="test_phone" class="form-control" placeholder="e.g. +233240000000">
                        </div>
                        <div class="mb-3">
                            <label class="input-label">Optional Short Note</label>
                            <input type="text" name="custom_note" maxlength="120" class="form-control" placeholder="e.g. High buyer demand in Kumasi &amp; Accra this week">
                        </div>
                        <button type="submit" class="btn btn-success w-100" onclick="return confirm('Send agro price SMS alert via Hubtel?')">
                            <i class="tio-send"></i> Send Hubtel SMS Price Alert
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Tracked Commodities ({{ $prices->count() }})</h5>
            <form action="{{ route('admin.agro-prices.index') }}" method="GET" class="d-flex gap-2">
                <input type="search" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search commodity or hub...">
                <button type="submit" class="btn btn-sm btn-secondary">Filter</button>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Commodity</th>
                        <th>Hub</th>
                        <th>Wholesale (GHS)</th>
                        <th>Retail (GHS)</th>
                        <th>Change %</th>
                        <th>Trend</th>
                        <th>Source</th>
                        <th>Active</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($prices as $price)
                        <tr>
                            <td>
                                <strong>{{ $price->commodity }}</strong>
                                <div class="text-muted small">{{ $price->category }} &bull; {{ $price->unit }}</div>
                            </td>
                            <form action="{{ route('admin.agro-prices.update', $price->id) }}" method="POST">
                                @csrf
                                <td>
                                    <input type="text" name="market_hub" value="{{ $price->market_hub }}" class="form-control form-control-sm" style="min-width:110px" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="wholesale_price_ghs" value="{{ $price->wholesale_price_ghs }}" class="form-control form-control-sm" style="min-width:95px" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" min="0" name="retail_price_ghs" value="{{ $price->retail_price_ghs }}" class="form-control form-control-sm" style="min-width:95px" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="price_change_pct" value="{{ $price->price_change_pct }}" class="form-control form-control-sm" style="min-width:80px">
                                </td>
                                <td>
                                    <select name="trend" class="form-control form-control-sm" style="min-width:95px">
                                        <option value="up" {{ $price->trend === 'up' ? 'selected' : '' }}>Up</option>
                                        <option value="down" {{ $price->trend === 'down' ? 'selected' : '' }}>Down</option>
                                        <option value="stable" {{ $price->trend === 'stable' ? 'selected' : '' }}>Stable</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="source_platform" value="{{ $price->source_platform }}" class="form-control form-control-sm" style="min-width:130px">
                                </td>
                                <td class="text-center">
                                    <input type="checkbox" name="is_active" value="1" {{ $price->is_active ? 'checked' : '' }}>
                                </td>
                                <td class="text-right text-nowrap">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                            </form>
                                    <form action="{{ route('admin.agro-prices.destroy', $price->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this price entry?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="tio-delete-outlined"></i></button>
                                    </form>
                                </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">No agro market prices found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
