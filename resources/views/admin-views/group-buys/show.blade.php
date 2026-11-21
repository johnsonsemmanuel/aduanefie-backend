@extends('layouts.admin.app')

@section('title', 'Group Buy #' . $group->id)

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">Group Buy #{{ $group->id }}</h1>
            <p class="text-muted mb-0">
                {{ $group->item?->name ?? 'Item removed' }} &middot; {{ $group->store?->name ?? 'Store removed' }}
                &middot; {{ $group->status }}
            </p>
        </div>
        <a href="{{ route('admin.group-buys.index') }}" class="btn btn-outline-secondary btn-sm">Back</a>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">Pool Summary</h5></div>
                <div class="card-body">
                    <p class="mb-1"><strong>Target value:</strong> GH₵ {{ number_format($group->target_value_ghs, 2) }}</p>
                    <p class="mb-1"><strong>Farmer price snapshot:</strong> GH₵ {{ number_format($group->farmer_price_ghs, 2) }}</p>
                    <p class="mb-1"><strong>Effective unit price:</strong> GH₵ {{ number_format($unitPrice, 2) }}</p>
                    <p class="mb-1"><strong>Pooled units:</strong> {{ $group->target_units }} ({{ $group->share_units }} per member)</p>
                    <p class="mb-1"><strong>Members:</strong> {{ $group->committed_count }} / {{ $group->member_limit }}</p>
                    <p class="mb-1"><strong>Paid:</strong> {{ $group->paidCount() }} &middot; <strong>Unpaid:</strong> {{ $group->unpaidCount() }}</p>
                    <p class="mb-1"><strong>Destination:</strong> {{ $group->destination_country ?? 'Domestic' }}</p>
                    <p class="mb-0"><strong>Closes:</strong> {{ $group->closes_at?->format('Y-m-d H:i') ?? '-' }}</p>
                    @if($group->escrow_status === 'ready')
                        <p class="mb-1"><strong>Escrow:</strong> <span class="badge badge-soft-success">Ready for release</span></p>
                    @elseif($group->escrow_status === 'released')
                        <p class="mb-1"><strong>Escrow:</strong> <span class="badge badge-soft-primary">Released</span></p>
                    @endif
                </div>
                @if($group->status === 'open')
                    <div class="card-footer d-flex gap-2">
                        <form action="{{ route('admin.group-buys.cancel', $group->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Cancel and release all members?')">Cancel group</button>
                        </form>
                        <form action="{{ route('admin.group-buys.expire', $group->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Expire and release all members?')">Expire now</button>
                        </form>
                    </div>
                @elseif($group->status === 'locked')
                    <div class="card-footer d-flex gap-2">
                        <form action="{{ route('admin.group-buys.remind', $group->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Send payment reminders to unpaid members?')">
                                <i class="tio-alarm"></i> Send Reminders
                            </button>
                        </form>
                        @if($group->escrow_status === 'ready')
                            <form action="{{ route('admin.group-buys.release-escrow', $group->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" onclick="return confirm('Release escrow funds to the store?')">
                                    <i class="tio-check-circle"></i> Release Escrow
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-3">
                <div class="card-header"><h5 class="card-title mb-0">Members</h5></div>
                <div class="table-responsive">
                    <table class="table table-sm card-table">
                        <thead class="thead-light">
                            <tr><th>#</th><th>Member</th><th>Share</th><th>Payment</th><th>Fulfilment</th><th></th></tr>
                        </thead>
                        <tbody>
                            @forelse($group->participants as $participant)
                                <tr>
                                    <td>{{ $participant->id }}</td>
                                    <td>{{ $participant->user_id ? 'User #' . $participant->user_id : 'Guest ' . $participant->guest_id }}</td>
                                    <td>GH₵ {{ number_format($participant->item_share_ghs, 2) }}<br><small class="text-muted">{{ $participant->share_units }} units</small></td>
                                    <td>{{ $participant->payment_status }}</td>
                                    <td>{{ $participant->fulfillment_status }}</td>
                                    <td>
                                        @if($group->status === 'locked' && $participant->fulfillment_status === 'pending')
                                            <form action="{{ route('admin.group-buys.ship', $participant->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-primary">Ship</button>
                                            </form>
                                        @elseif($participant->fulfillment_status === 'shipped')
                                            <form action="{{ route('admin.group-buys.deliver', $participant->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button type="submit" class="btn btn-sm btn-outline-success">Delivered</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted">No members yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Event trail</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($group->events as $event)
                            <li class="mb-1">
                                <span class="badge badge-soft-secondary">{{ $event->event }}</span>
                                <small class="text-muted">{{ $event->created_at?->format('Y-m-d H:i:s') }}</small>
                                @if($event->meta)
                                    <small class="text-muted d-block">{{ json_encode($event->meta) }}</small>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
