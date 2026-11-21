@extends('layouts.admin.app')

@section('title', 'Shared Group Buys')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group"></i></span>
                <span>Shared Group Buys</span>
            </h1>
            <p class="text-muted mb-0">
                Monitor pooled purchases. Groups fill with five equal shares; no member is charged until the group is complete.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">All Groups ({{ $groups->total() }})</h5>
            <form action="{{ route('admin.group-buys.index') }}" method="GET" class="d-flex gap-2">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                    @foreach(['all', 'open', 'locked', 'fulfilled', 'cancelled', 'expired'] as $option)
                        <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>
                            {{ ucfirst($option) }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Group #</th>
                        <th>Item / Store</th>
                        <th>Target</th>
                        <th>Members</th>
                        <th>Status</th>
                        <th>Closes</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($groups as $group)
                        <tr>
                            <td>#{{ $group->id }}</td>
                            <td>
                                <div class="font-weight-bold">{{ $group->item?->name ?? 'Item removed' }}</div>
                                <div class="text-muted small">{{ $group->store?->name ?? 'Store removed' }}</div>
                            </td>
                            <td>
                                <div>GH₵ {{ number_format($group->target_value_ghs, 2) }}</div>
                                <div class="text-muted small">{{ $group->target_units }} units total / {{ $group->share_units }} each</div>
                            </td>
                            <td>{{ $group->committed_count }} / {{ $group->member_limit }}</td>
                            <td><span class="badge badge-soft-primary">{{ $group->status }}</span></td>
                            <td>{{ $group->closes_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td>
                                <a href="{{ route('admin.group-buys.show', $group->id) }}" class="btn btn-sm btn-outline-primary">
                                    View
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No group buys yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">{{ $groups->links() }}</div>
    </div>
</div>
@endsection
