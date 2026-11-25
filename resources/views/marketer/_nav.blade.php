@php
    $active = $active ?? '';
    $currentMarketer = $marketer ?? \Illuminate\Support\Facades\Auth::guard('admin')->user();
    $accessService = app(\App\Services\MarketerAccessService::class);

    if (isset($pendingBackyardCount)) {
        $navPendingBackyard = (int) $pendingBackyardCount;
    } elseif ($currentMarketer) {
        $bq = $accessService->backyardQuery($currentMarketer);
        $navPendingBackyard = $bq ? $bq->where('status', 'pending')->count() : 0;
    } else {
        $navPendingBackyard = 0;
    }

    if (isset($upcomingToursCount)) {
        $navOpenTours = (int) $upcomingToursCount;
    } elseif ($currentMarketer) {
        $tq = $accessService->tourQuery($currentMarketer);
        $navOpenTours = $tq ? $tq->whereIn('status', ['upcoming', 'open', 'in_progress'])->count() : 0;
    } else {
        $navOpenTours = 0;
    }
@endphp
<a href="{{ route('marketer.dashboard') }}" class="{{ $active === 'dashboard' ? 'active' : '' }}">Dashboard</a>
<a href="{{ route('marketer.register-farmer') }}" class="{{ $active === 'register' ? 'active' : '' }}">Register Farmer</a>
<a href="{{ route('marketer.backyard-farms') }}" class="{{ $active === 'backyard' ? 'active' : '' }}">
    Backyard Farms
    @if($navPendingBackyard > 0)
        <span class="badge ml-1" style="background:#8B9F69;color:#fff;">{{ $navPendingBackyard }}</span>
    @endif
</a>
<a href="{{ route('marketer.regional-tours') }}" class="{{ $active === 'tours' ? 'active' : '' }}">
    Regional Tours
    @if($navOpenTours > 0)
        <span class="badge ml-1" style="background:#67513A;color:#fff;">{{ $navOpenTours }}</span>
    @endif
</a>
<a href="{{ route('marketer.commissions') }}" class="{{ $active === 'commissions' ? 'active' : '' }}">Commissions</a>
<a href="{{ route('marketer.payouts') }}" class="{{ $active === 'payouts' ? 'active' : '' }}">Payouts</a>
<form method="post" action="{{ route('marketer.logout') }}" style="margin:0 0 0 auto;">
    @csrf
    <button type="submit" class="btn secondary">Sign out</button>
</form>
