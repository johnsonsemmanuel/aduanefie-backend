@extends('layouts.admin.app')

@section('title', 'Customer Pro Plans')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <i class="tio-crown"></i>
                        </span>
                        <span>Customer Pro Membership Plans</span>
                        <span class="badge badge-soft-success ml-2">{{ $activeSubscribers }} active</span>
                    </h1>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Add New Pro Plan</h5>
            </div>
            <div class="card-body">
                <form method="post" action="{{ route('admin.pro-plans.store') }}" class="row g-3">
                    @csrf
                    <div class="col-md-3">
                        <label class="input-label">Plan Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Fresh Monthly" required>
                    </div>
                    <div class="col-md-3">
                        <label class="input-label">Tagline</label>
                        <input type="text" name="tagline" class="form-control" placeholder="Short benefit summary">
                    </div>
                    <div class="col-md-2">
                        <label class="input-label">Price *</label>
                        <input type="number" step="0.01" min="0" name="price" class="form-control" value="29.00" required>
                    </div>
                    <div class="col-md-2">
                        <label class="input-label">Validity (Days) *</label>
                        <input type="number" min="1" name="validity_days" class="form-control" value="30" required>
                    </div>
                    <div class="col-md-2">
                        <label class="input-label">Min Order *</label>
                        <input type="number" step="0.01" min="0" name="min_order_amount" class="form-control" value="100.00" required>
                    </div>
                    <div class="col-12 mt-3">
                        <button type="submit" class="btn btn--primary">Create Plan</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="table-responsive">
                <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Plan</th>
                            <th>Tagline</th>
                            <th>Price</th>
                            <th>Days</th>
                            <th>Min Order</th>
                            <th>Active</th>
                            <th>Save</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <form method="post" action="{{ route('admin.pro-plans.update', $plan->id) }}">
                                @csrf
                                <td><input type="text" name="name" value="{{ $plan->name }}" class="form-control form-control-sm" required></td>
                                <td><input type="text" name="tagline" value="{{ $plan->tagline }}" class="form-control form-control-sm"></td>
                                <td style="width:110px;"><input type="number" step="0.01" min="0" name="price" value="{{ $plan->price }}" class="form-control form-control-sm" required></td>
                                <td style="width:90px;"><input type="number" min="1" name="validity_days" value="{{ $plan->validity_days }}" class="form-control form-control-sm" required></td>
                                <td style="width:110px;"><input type="number" step="0.01" min="0" name="min_order_amount" value="{{ $plan->min_order_amount }}" class="form-control form-control-sm" required></td>
                                <td>
                                    <input type="checkbox" name="status" value="1" {{ $plan->status ? 'checked' : '' }}>
                                </td>
                                <td>
                                    <button type="submit" class="btn btn-sm btn--primary">Update</button>
                                </td>
                            </form>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No plans created yet.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
