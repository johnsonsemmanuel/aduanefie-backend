@php
    $active = $active ?? '';
    $navPendingBackyard = \Illuminate\Support\Facades\Schema::hasTable('backyard_farm_applications')
        ? \App\Models\BackyardFarmApplication::where('status', 'pending')->count()
        : 0;
    $navOpenTours = \Illuminate\Support\Facades\Schema::hasTable('regional_tours')
        ? \App\Models\RegionalTour::whereIn('status', ['upcoming', 'open', 'in_progress'])->count()
        : 0;
@endphp
<a href="{{ route('marketer.dashboard') }}" class="{{ $active === 'dashboard' ? 'active' : '' }}">Dashboard</a>
<a href="{{ route('marketer.register-farmer') }}" class="{{ $active === 'register' ? 'active' : '' }}">+ Register Farmer</a>
<a href="{{ route('marketer.backyard-farms') }}" class="{{ $active === 'backyard' ? 'active' : '' }}">
    Backyard Farms
    @if($navPendingBackyard > 0)
        <span class="badge" style="margin-left:4px;background:#8B9F69;color:#fff;">{{ $navPendingBackyard }}</span>
    @endif
</a>
<a href="{{ route('marketer.regional-tours') }}" class="{{ $active === 'tours' ? 'active' : '' }}">
    Regional Tours
    @if($navOpenTours > 0)
        <span class="badge" style="margin-left:4px;background:#67513A;color:#fff;">{{ $navOpenTours }}</span>
    @endif
</a>
<a href="{{ route('marketer.commissions') }}" class="{{ $active === 'commissions' ? 'active' : '' }}">Commissions</a>
<a href="{{ route('marketer.payouts') }}" class="{{ $active === 'payouts' ? 'active' : '' }}">Payouts</a>
<form method="post" action="{{ route('marketer.logout') }}" style="margin:0 0 0 auto;">
    @csrf
    <button type="submit" class="btn secondary" style="padding:8px 13px;font-size:14px;">Sign out</button>
</form>
