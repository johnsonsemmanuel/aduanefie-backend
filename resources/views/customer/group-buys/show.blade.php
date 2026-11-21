@extends('layouts.app')

@section('title', 'Group Buy #' . $group->id)

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-group"></i></span>
                <span>Group Buy #{{ $group->id }}</span>
            </h1>
            <p class="text-muted mb-0">
                {{ $group->item?->name ?? 'Item' }} &middot; {{ $group->store?->name ?? 'Store' }}
                &middot; <span class="badge badge-soft-{{ $group->status === 'open' ? 'primary' : 'success' }}">{{ ucfirst($group->status) }}</span>
            </p>
        </div>
        <a href="{{ route('customer.group-buys.index') }}" class="btn btn-outline-secondary btn-sm">Back to groups</a>
        <button class="btn btn-outline-primary btn-sm" onclick="copyGroupLink()" id="copyBtn">
            <i class="tio-link"></i> Share Group
        </button>
    </div>

    <input type="hidden" id="groupLink" value="{{ $shareLinks['deep'] ?? route('customer.group-buys.show', $group->id) }}">
    <input type="hidden" id="groupLinkWeb" value="{{ route('customer.group-buys.show', $group->id) }}">

    @if ($group->status === 'open' && ! $participant)
        @include('customer.group-buys.partials.consent_modal', ['group' => $group])
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Group Details</h5></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Item:</strong> {{ $group->item?->name ?? 'Item removed' }}</p>
                            <p class="mb-1"><strong>Store:</strong> {{ $group->store?->name ?? 'Store removed' }}</p>
                            <p class="mb-1"><strong>Target value:</strong> GH₵ {{ number_format($group->target_value_ghs, 2) }}</p>
                            <p class="mb-1"><strong>Farmer price:</strong> GH₵ {{ number_format($group->farmer_price_ghs, 2) }}</p>
                            <p class="mb-1"><strong>Your share price:</strong> GH₵ {{ number_format($memberShare, 2) }}</p>
                        </div>
                        <div class="col-md-6">
                            <p class="mb-1"><strong>Units per member:</strong> {{ $group->share_units }}</p>
                            <p class="mb-1"><strong>Total pooled:</strong> {{ $group->target_units }} units</p>
                            <p class="mb-1"><strong>Members:</strong> {{ $group->committed_count }} / {{ $group->member_limit }}</p>
                            <p class="mb-0"><strong>Closes:</strong> {{ $group->closes_at?->format('Y-m-d H:i') ?? '-' }}</p>
                        </div>
                    </div>

                    @if ($group->consent_text)
                        <div class="alert alert-info mt-3">
                            <h6>Group Consent Terms</h6>
                            <p class="mb-0">{{ $group->consent_text }}</p>
                        </div>
                    @endif

                    @if ($group->status === 'open' && ! $participant && Auth::guard('web')->check())
                        <form action="{{ route('customer.group-buys.join', $group->id) }}" method="POST" class="mt-4">
                            @csrf
                            <div class="form-group">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="consent_accepted" name="consent_accepted" required>
                                    <label class="custom-control-label" for="consent_accepted">
                                        I have read and accept the group consent terms above.
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                Join this group
                            </button>
                        </form>
                    @elseif ($participant)
                        <div class="alert alert-success mt-3">
                            <strong>You're already a member.</strong> Your share: GH₵ {{ number_format($participant->item_share_ghs, 2) }} ({{
                            $participant->share_units }} units). Status: {{ ucfirst($participant->payment_status) }}.
                        </div>
                        @if ($group->status === 'locked' && $participant->payment_status === 'not_charged')
                            <a href="{{ route('customer.group-buys.checkout', $group->id) }}" class="btn btn-primary mt-2">Checkout your share</a>
                        @endif
                    @elseif ($group->status !== 'open')
                        <div class="alert alert-info mt-3">
                            This group is {{ $group->status }}. {{ $group->status === 'locked' ? 'Checkout is open for members.' : '' }}
                        </div>
                    @elseif (! Auth::guard('web')->check())
                        <div class="alert alert-warning mt-3">
                            <a href="{{ route('login') }}" class="alert-link">Sign in</a> to join this group.
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Members</h5></div>
                <div class="card-body">
                    <table class="table table-sm card-table">
                        <thead class="thead-light">
                            <tr><th>Member</th><th>Share</th><th>Payment</th><th>Joined</th></tr>
                        </thead>
                        <tbody>
                            @forelse($group->participants as $p)
                                <tr>
                                    <td>
                                        {{ $p->user ? $p->user->f_name . ' ' . $p->user->l_name : 'Guest ' . $p->guest_id }}
                                        @if ($p->user && (int) $p->user_id === (int) ($userId ?? 0))
                                            <span class="badge badge-soft-success ml-2">You</span>
                                        @endif
                                    </td>
                                    <td>GH₵ {{ number_format($p->item_share_ghs, 2) }}</td>
                                    <td>
                                        <span class="badge badge-soft-{{ $p->payment_status === 'paid' || $p->payment_status === 'ordered' ? 'success' : 'warning' }}">
                                            {{ ucfirst(str_replace('_', ' ', $p->payment_status)) }}
                                        </span>
                                    </td>
                                    <td>{{ $p->joined_at?->format('M d, H:i') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="text-center text-muted">No members yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header"><h5 class="card-title mb-0">Event Trail</h5></div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        @foreach($group->events as $event)
                            <li class="mb-1">
                                <span class="badge badge-soft-secondary">{{ $event->event }}</span>
                                <small class="text-muted">{{ $event->created_at?->format('M d, H:i') }}</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4 sticky-top" style="top: 1rem;">
                <div class="card-body">
                    <h6 class="mb-3">Your Summary</h6>
                    @if ($participant)
                        <p class="mb-1"><strong>Your share:</strong> GH₵ {{ number_format($participant->item_share_ghs, 2) }}</p>
                        <p class="mb-1"><strong>Units:</strong> {{ $participant->share_units }}</p>
                        <p class="mb-1"><strong>Payment:</strong>
                            <span class="{{ $participant->payment_status === 'paid' || $participant->payment_status === 'ordered' ? 'text-success' : 'text-warning' }}">
                                {{ ucfirst(str_replace('_', ' ', $participant->payment_status)) }}
                            </span>
                        </p>
                        @if ($group->status === 'locked' && $participant->payment_status === 'not_charged')
                            <a href="{{ route('customer.group-buys.checkout', $group->id) }}" class="btn btn-primary btn-block mt-3">Checkout your share</a>
                        @endif
                    @else
                        <p class="mb-1"><strong>Share price:</strong> GH₵ {{ number_format($memberShare, 2) }}</p>
                        @if (Auth::guard('web')->check() && $group->isOpen())
                            <a href="#join" class="btn btn-primary btn-block mt-3">Join this group</a>
                        @endif
                    @endif
                </div>

                @if ($group->closes_at)
                    <div class="card-footer bg-transparent border-0 p-3">
                        <h6 class="mb-2">Group Closes In</h6>
                        <div class="countdown-display font-weight-bold text-primary" id="countdown" data-end="{{ $group->closes_at->format('Y-m-d H:i:s') }}">--:--:--</div>
                        <div class="text-muted small mt-1">After this time, no more members can join.</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Auto-open consent modal on group detail page
    document.addEventListener('DOMContentLoaded', function () {
        var consentModal = new bootstrap.Modal(document.getElementById('consentModal'));
        consentModal.show();

        // Countdown timer
        var endEl = document.getElementById('countdown');
        if (endEl) {
            var endTime = new Date(endEl.getAttribute('data-end')).getTime();
            function updateCountdown() {
                var now = new Date().getTime();
                var diff = endTime - now;
                if (diff <= 0) {
                    endEl.textContent = 'Group closed';
                    return;
                }
                var h = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                var m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                var s = Math.floor((diff % (1000 * 60)) / 1000);
                endEl.textContent =
                    (h < 10 ? '0' : '') + h + ':' +
                    (m < 10 ? '0' : '') + m + ':' +
                    (s < 10 ? '0' : '') + s;
            }
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }

        // Copy group link
        window.copyGroupLink = function () {
            var linkEl = document.getElementById('groupLink');
            var webEl = document.getElementById('groupLinkWeb');
            var btn = document.getElementById('copyBtn');
            if (!linkEl) return;
            var link = linkEl.value;
            var webLink = webEl ? webEl.value : link;
            navigator.clipboard.writeText(link).then(function () {
                var orig = btn.innerHTML;
                btn.innerHTML = '<i class="tio-check"></i> Copied (app link)!';
                setTimeout(function () {
                    btn.innerHTML = orig;
                    // After 2s, offer the web link too
                    var toast = document.createElement('div');
                    toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
                    toast.style.bottom = '1rem';
                    toast.style.right = '1rem';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = '<strong>Deep link copied:</strong> ' + link +
                        '<br><small class="text-muted">Web: ' + webLink + '</small>' +
                        '<button type="button" class="close" data-bs-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>';
                    document.body.appendChild(toast);
                    setTimeout(function () {
                        var bsAlert = new bootstrap.Alert(toast);
                        bsAlert.close();
                    }, 5000);
                }, 1500);
            });
        };
    });
</script>
@endpush
@endsection
