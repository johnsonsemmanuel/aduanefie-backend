@extends('layouts.admin.app')

@section('title', 'Cross-Border Market Rates')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <i class="tio-globe"></i>
                </span>
                <span>Cross-Border Market Rates</span>
            </h1>
            <p class="text-muted mb-0">
                Manage the FX conversions and shipping lane prices used by cross-border checkout quotes. Entries marked <strong>manual_baseline</strong> are indicative and are not live market data.
            </p>
        </div>
        <form action="{{ route('admin.market-rates.refresh-fx') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="tio-refresh"></i> Refresh FX from live provider
            </button>
        </form>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="tio-add-circle"></i> Add / update FX rate</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.market-rates.currency.store') }}" method="POST">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="input-label">From currency</label>
                                <input type="text" name="base_currency" class="form-control" placeholder="USD" maxlength="3" required>
                            </div>
                            <div class="col-md-6">
                                <label class="input-label">To currency</label>
                                <input type="text" name="quote_currency" class="form-control" placeholder="XOF" maxlength="3" required>
                            </div>
                            <div class="col-md-6">
                                <label class="input-label">Rate</label>
                                <input type="number" step="0.00000001" min="0.00000001" name="rate" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="input-label">Effective at (optional)</label>
                                <input type="datetime-local" name="effective_at" class="form-control">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Save FX rate</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="tio-add-circle"></i> Add shipping lane rate</h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('admin.market-rates.shipping.store') }}" method="POST">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="input-label">Method</label>
                                <select name="shipping_method_id" class="form-control" required>
                                    @foreach($shippingMethods as $method)
                                        <option value="{{ $method->id }}">{{ $method->name }} ({{ $method->code }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="input-label">Origin (ISO)</label>
                                <input type="text" name="origin_country" class="form-control" placeholder="GH" maxlength="2" required>
                            </div>
                            <div class="col-md-4">
                                <label class="input-label">Destination (ISO)</label>
                                <input type="text" name="destination_country" class="form-control" placeholder="TG" maxlength="2" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Base rate</label>
                                <input type="number" step="0.001" min="0" name="base_rate" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Per kg</label>
                                <input type="number" step="0.001" min="0" name="per_kg_rate" class="form-control" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Currency</label>
                                <input type="text" name="currency_code" class="form-control" value="USD" maxlength="3" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Transit days (min / max)</label>
                                <div class="d-flex gap-1">
                                    <input type="number" min="0" name="min_days" class="form-control" placeholder="2">
                                    <input type="number" min="0" name="max_days" class="form-control" placeholder="5">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Min weight</label>
                                <input type="number" step="0.001" min="0" name="min_weight" class="form-control" value="0" required>
                            </div>
                            <div class="col-md-3">
                                <label class="input-label">Max weight (optional)</label>
                                <input type="number" step="0.001" min="0" name="max_weight" class="form-control" placeholder="30">
                            </div>
                            <div class="col-md-6 d-flex align-items-end gap-3 mb-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="cod_allowed" value="1" id="add_cod">
                                    <label class="form-check-label" for="add_cod">COD allowed</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="add_active" checked>
                                    <label class="form-check-label" for="add_active">Active</label>
                                </div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Add lane rate</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">
            <h5 class="card-title mb-0">FX rates</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Pair</th>
                            <th>Rate</th>
                            <th>Source</th>
                            <th>Effective at</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($currencyRates as $row)
                            <tr>
                                <td>{{ $row->base_currency }} to {{ $row->quote_currency }}</td>
                                <td>
                                    <form action="{{ route('admin.market-rates.currency.update', $row->id) }}" method="POST" class="d-flex gap-1">
                                        @csrf
                                        <input type="number" step="0.00000001" min="0.00000001" name="rate" value="{{ $row->rate }}" class="form-control form-control-sm" style="width:140px" required>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                </td>
                                <td>{{ $row->source ?: 'legacy' }}</td>
                                <td>{{ optional($row->effective_at)->toDateTimeString() ?: 'not set (indicative)' }}</td>
                                <td>
                                    <form action="{{ route('admin.market-rates.currency.destroy', $row->id) }}" method="POST" onsubmit="return confirm('Delete this rate?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted">No FX rates configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Shipping lane rates</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm table-bordered">
                    <thead>
                        <tr>
                            <th>Lane</th>
                            <th>Method</th>
                            <th>Base / per kg</th>
                            <th>Transit</th>
                            <th>Source</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($shippingRates as $row)
                            <tr>
                                <td>{{ $row->origin_country }} to {{ $row->destination_country }}</td>
                                <td>{{ $row->method->name ?? ('Method #' . $row->shipping_method_id) }}</td>
                                <td>
                                    <form action="{{ route('admin.market-rates.shipping.update', $row->id) }}" method="POST" class="d-flex gap-1 align-items-center">
                                        @csrf
                                        <input type="number" step="0.001" min="0" name="base_rate" value="{{ $row->base_rate }}" class="form-control form-control-sm" style="width:90px" required>
                                        <input type="number" step="0.001" min="0" name="per_kg_rate" value="{{ $row->per_kg_rate }}" class="form-control form-control-sm" style="width:90px" required>
                                        <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                    </form>
                                </td>
                                <td>{{ $row->min_days }}-{{ $row->max_days }} days</td>
                                <td>{{ $row->source ?: 'legacy' }}</td>
                                <td>{{ $row->is_active ? 'Active' : 'Inactive' }}</td>
                                <td>
                                    <form action="{{ route('admin.market-rates.shipping.destroy', $row->id) }}" method="POST" onsubmit="return confirm('Delete this lane rate?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted">No shipping lane rates configured yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
