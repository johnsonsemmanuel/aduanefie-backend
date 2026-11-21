@extends('layouts.app')

@section('title', 'Group Buys')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group"></i></span>
                <span>Group Buys</span>
            </h1>
            <p class="text-muted mb-0">
                Buy together, save together. Groups fill with equal shares; no one is charged until the group is complete.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Open Groups ({{ $groups->total() }})</h5>
        </div>

        <div class="card-body">
            @forelse($groups as $group)
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $group->item?->name ?? 'Item' }}</h6>
                                <p class="text-muted small mb-2">{{ $group->store?->name ?? 'Store' }} &middot; {{ $group->destination_country ?? 'Domestic' }}</p>

                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <div class="font-weight-bold">GH₵ {{ number_format($group->target_value_ghs, 2) }}</div>
                                        <div class="text-muted small">Target value</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="font-weight-bold">{{ $group->committed_count }} / {{ $group->member_limit }}</div>
                                        <div class="text-muted small">Members</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="font-weight-bold">{{ $group->closes_at?->format('M d, H:i') ?? '-' }}</div>
                                        <div class="text-muted small">Closes</div>
                                    </div>
                                </div>

                                <div class="progress mb-2" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: {{ (($group->committed_count / $group->member_limit) * 100) }}%"></div>
                                </div>
                            </div>

                            <div class="text-right">
                                @if (Auth::guard('web')->check())
                                    <a href="{{ route('customer.group-buys.show', $group->id) }}" class="btn btn-primary btn-sm">
                                        Join Group
                                    </a>
                                @else
                                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                                        Sign in to join
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <p class="text-muted">No open group buys right now. Check back soon.</p>
                </div>
            @endforelse
        </div>

        <div class="card-footer">{{ $groups->links() }}</div>
    </div>
</div>
@endsection
