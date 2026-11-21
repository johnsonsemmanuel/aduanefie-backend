@extends('layouts.admin.app')

@section('title', 'Backyard Farm Applications')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-leaf"></i></span>
                <span>Backyard Farm Applications</span>
            </h1>
            <p class="text-muted mb-0">
                Review and verify backyard farm applications. Verified farms become vendor stores
                with farm_type = 'backyard' and their products appear in nearby farming discovery.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Applications ({{ $applications->total() }})</h5>
            <div class="d-flex gap-2">
                <form action="{{ route('admin.backyard-farmers.index') }}" method="GET" class="d-flex gap-2">
                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                        @foreach(['all', 'pending', 'verified', 'approved', 'rejected'] as $option)
                            <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>{{ ucfirst($option) }}</option>
                        @endforeach
                    </select>
                </form>
                <a href="{{ route('admin.backyard-farmers.export', ['status' => $status]) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="tio-download"></i> Export CSV
                </a>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>#</th>
                        <th>Farm</th>
                        <th>Crops</th>
                        <th>Size</th>
                        <th>Location</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                        <tr>
                            <td>#{{ $app->id }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $app->farm_name }}</div>
                                <div class="text-muted small">{{ $app->created_at?->format('M d, Y') }}</div>
                            </td>
                            <td class="text-muted small">{{ $app->primary_crops }}</td>
                            <td>{{ number_format($app->farm_size_acres, 2) }} acres</td>
                            <td class="text-muted small">{{ $app->address }}</td>
                            <td class="text-muted small">
                                {{ $app->guest_email }}<br>{{ $app->guest_phone }}
                            </td>
                            <td>
                                @switch($app->status)
                                    @case('pending') <span class="badge badge-soft-warning">Pending</span> @break
                                    @case('verified') <span class="badge badge-soft-info">Verified</span> @break
                                    @case('approved') <span class="badge badge-soft-success">Approved</span> @break
                                    @case('rejected') <span class="badge badge-soft-danger">Rejected</span> @break
                                @endswitch
                            </td>
                            <td>
                                @if($app->isPending())
                                    <a href="{{ route('admin.backyard-farmers.verify', $app->id) }}" class="btn btn-sm btn-outline-primary">
                                        Verify
                                    </a>
                                @elseif($app->isVerified())
                                    <form action="{{ route('admin.backyard-farmers.approve', $app->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this backyard farm and create vendor store?')">
                                            Approve
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No backyard farm applications.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
