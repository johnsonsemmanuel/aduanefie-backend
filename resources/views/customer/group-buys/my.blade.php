@extends('layouts.app')

@section('title', 'My Group Buys')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group"></i></span>
                <span>My Group Buys</span>
            </h1>
            <p class="text-muted mb-0">Groups you've joined or organised.</p>
        </div>
        <a href="{{ route('customer.group-buys.index') }}" class="btn btn-primary btn-sm">Browse groups</a>
    </div>

    <div class="card">
        <div class="card-body">
            @forelse($groups as $group)
                <div class="card mb-3 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">{{ $group->item?->name ?? 'Item' }}</h6>
                                <p class="text-muted small mb-2">{{ $group->store?->name ?? 'Store' }} &middot; {{ ucfirst($group->status) }}</p>

                                <div class="row mb-2">
                                    <div class="col-md-4">
                                        <div class="font-weight-bold">GH₵ {{ number_format($group->target_value_ghs, 2) }}</div>
                                        <div class="text-muted small">Target</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="font-weight-bold">{{ $group->committed_count }} / {{ $group->member_limit }}</div>
                                        <div class="text-muted small">Members</div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="font-weight-bold">{{ $group->paidCount() }} paid</div>
                                        <div class="text-muted small">Paid / {{ $group->member_limit }}</div>
                                    </div>
                                </div>

                                @if ($group->status === 'open')
                                    @if (Auth::guard('web')->user() && $group->participants->contains('user_id', Auth::guard('web')->user()->id))
                                        <a href="{{ route('customer.group-buys.show', $group->id) }}" class="btn btn-outline-primary btn-sm">View group</a>
                                        <form action="{{ route('customer.group-buys.leave', $group->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-outline-danger btn-sm">Leave group</button>
                                        </form>
                                    @elseif (Auth::guard('web')->user() && (int) $group->organizer_id === (int) Auth::guard('web')->user()->id)
                                        <a href="{{ route('customer.group-buys.show', $group->id) }}" class="btn btn-primary btn-sm">Manage group</a>
                                    @else
                                        <a href="{{ route('customer.group-buys.show', $group->id) }}" class="btn btn-primary btn-sm">Join group</a>
                                    @endif
                                @elseif ($group->status === 'locked')
                                    <a href="{{ route('customer.group-buys.show', $group->id) }}" class="btn btn-primary btn-sm">Checkout your share</a>
                                @else
                                    <span class="badge badge-soft-secondary">{{ ucfirst($group->status) }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center py-5">
                    <p class="text-muted">You haven't joined any groups yet.</p>
                    <a href="{{ route('customer.group-buys.index') }}" class="btn btn-primary">Browse groups</a>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
