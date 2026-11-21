@extends('layouts.vendor.app')

@section('title', 'Backyard Farm Dashboard')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-leaf"></i></span>
                <span>Backyard Farm Dashboard</span>
            </h1>
            <p class="text-muted mb-0">
                Manage your backyard farm products. Items marked "harvest ready" appear in nearby farming discovery with a "Backyard Grown" badge.
            </p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Farm Size</h6>
                    <p class="mb-1 font-weight-bold">{{ number_format($store->farm_size_acres ?? 0, 2) }} acres</p>
                    <h6 class="text-muted mb-2">Primary Crops</h6>
                    <p class="mb-0 text-muted small">{{ $store->primary_crops ?? 'Not set' }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Total Products</h6>
                    <p class="mb-1 font-weight-bold">{{ $items->total() }}</p>
                    <h6 class="text-muted mb-2">Backyard Products</h6>
                    <p class="mb-0 text-success font-weight-bold">{{ $items->where('is_backyard', true)->count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Harvest Ready</h6>
                    <p class="mb-1 font-weight-bold">{{ $items->where('farming_status', 'harvest_ready')->count() }}</p>
                    <h6 class="text-muted mb-2">Currently Farming</h6>
                    <p class="mb-0 text-warning font-weight-bold">{{ $items->where('farming_status', 'farming')->count() }}</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card mb-3">
                <div class="card-body text-center">
                    <h6 class="text-muted mb-2">Cultivation Status</h6>
                    <p class="mb-1 font-weight-bold">{{ ucfirst($store->cultivation_status ?? 'active') }}</p>
                    @if ($store->next_harvest_date)
                        <h6 class="text-muted mb-2">Next Harvest</h6>
                        <p class="mb-0 text-muted small">{{ \Carbon\Carbon::parse($store->next_harvest_date)->format('M d, Y') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if ($farmAnalytics)
        <div class="row mt-4">
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Estimated Total Yield</h6>
                        <p class="mb-1 font-weight-bold">{{ number_format($farmAnalytics['total_estimated_yield_kg'], 1) }} kg</p>
                        <h6 class="text-muted mb-2">Plantable Area</h6>
                        <p class="mb-0 text-muted small">{{ number_format($farmAnalytics['size_sqm'], 0) }} m²</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Next Harvest</h6>
                        @if ($farmAnalytics['next_harvest_date'])
                            <p class="mb-1 font-weight-bold">{{ \Carbon\Carbon::parse($farmAnalytics['next_harvest_date'])->format('M d, Y') }}</p>
                            <p class="mb-0 text-{{ $farmAnalytics['days_to_harvest'] <= 7 ? 'warning' : 'success' }} small">
                                {{ $farmAnalytics['days_to_harvest'] }} days away
                            </p>
                        @else
                            <p class="mb-0 text-muted small">No scheduled harvest</p>
                        @endif
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Yield by Crop</h6>
                        @foreach($farmAnalytics['estimated_yield_per_crop'] as $crop => $yield)
                            <div class="d-flex justify-content-between mb-1">
                                <span class="text-muted small">{{ ucfirst($crop) }}</span>
                                <span class="font-weight-bold">{{ number_format($yield, 1) }} kg</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header"><h5 class="card-title mb-0">Crop Rotation Suggestion</h5></div>
            <div class="card-body">
                <div class="alert alert-info">
                    {{ $farmAnalytics['crop_rotation_suggestion'] }}
                </div>
            </div>
        </div>
    @endif

    <div class="card mt-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Your Products</h5>
            <a href="{{ route('vendor.item.create') ?? '/vendor/item/create' }}" class="btn btn-primary btn-sm">Add Product</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover table-borderless card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Product</th>
                        <th>Badges</th>
                        <th>Farming Status</th>
                        <th>Price</th>
                        <th>Approved</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($items as $item)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    @if ($item->image_full_url)
                                        <img src="{{ $item->image_full_url }}" class="rounded" width="40" height="40">
                                    @endif
                                    <div class="ml-3">
                                        <div class="font-weight-bold">{{ $item->name }}</div>
                                        <div class="text-muted small">#{{ $item->id }}</div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if (isset($item->badges) && is_array($item->badges))
                                    @foreach($item->badges as $badge)
                                        <span class="badge badge-soft-{{ $badge['type'] ?? 'secondary' }}">{{ $badge['label'] }}</span>
                                    @endforeach
                                @else
                                    <span class="badge badge-soft-secondary">Standard</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge badge-soft-{{ $item->farming_status === 'harvest_ready' ? 'success' : ($item->farming_status === 'farming' ? 'warning' : 'secondary') }}">
                                    {{ ucfirst($item->farming_status ?? 'in_stock') }}
                                </span>
                            </td>
                            <td>GH₵ {{ number_format($item->price, 2) }}</td>
                            <td>
                                @if ($item->is_approved)
                                    <span class="badge badge-soft-success">Yes</span>
                                @else
                                    <span class="badge badge-soft-warning">Pending</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('vendor.item.edit', $item->id) ?? ('/vendor/item/' . $item->id . '/edit') }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No products yet. Add your first backyard product to start selling.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $items->links() }}</div>
    </div>
</div>
@endsection
