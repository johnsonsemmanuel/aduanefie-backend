@php($active = $active ?? '')
<a href="{{ route('marketer.dashboard') }}" class="{{ $active === 'dashboard' ? 'active' : '' }}">Dashboard</a>
<a href="{{ route('marketer.commissions') }}" class="{{ $active === 'commissions' ? 'active' : '' }}">Commissions</a>
<a href="{{ route('marketer.payouts') }}" class="{{ $active === 'payouts' ? 'active' : '' }}">Payouts</a>
<form method="post" action="{{ route('marketer.logout') }}" style="margin:0 0 0 auto;">
    @csrf
    <button type="submit" class="btn secondary" style="padding:8px 13px;font-size:14px;">Sign out</button>
</form>
