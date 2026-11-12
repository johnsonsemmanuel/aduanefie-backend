@extends('marketer.layout')

@section('title', 'Request a payout')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'payouts'])
@endsection

@section('content')
    <div class="card" style="max-width:560px;">
        <h1>Request a payout</h1>
        <p class="muted small" style="margin-top:0;">
            You are requesting <strong>{{ number_format($summary['eligible'], 2) }} {{ $summary['currency'] }}</strong>
            from {{ $summary['eligible_count'] }} eligible {{ $summary['eligible_count'] === 1 ? 'commission' : 'commissions' }}.
            Enter the mobile-money account the money should be sent to.
        </p>

        <form method="post" action="{{ route('marketer.payouts.store') }}">
            @csrf

            <div class="field">
                <label for="network">Mobile money network</label>
                <input type="text" name="network" id="network" value="{{ old('network') }}"
                       placeholder="MTN, Vodafone, AirtelTigo, M-Pesa…" required maxlength="50">
            </div>

            <div class="field">
                <label for="account_name">Account name</label>
                <input type="text" name="account_name" id="account_name" value="{{ old('account_name') }}"
                       required maxlength="100">
            </div>

            <div class="field">
                <label for="account_number">Account number</label>
                <input type="tel" name="account_number" id="account_number" value="{{ old('account_number') }}"
                       required maxlength="50">
            </div>

            <div class="field">
                <label for="country">Country</label>
                <input type="text" name="country" id="country" value="{{ old('country') }}"
                       placeholder="GH, NG, KE…" required maxlength="10">
            </div>

            <div class="field">
                <label for="note">Note (optional)</label>
                <textarea name="note" id="note" rows="3" maxlength="500">{{ old('note') }}</textarea>
            </div>

            <button type="submit">Submit request</button>
            <a class="btn secondary" href="{{ route('marketer.payouts') }}">Cancel</a>
        </form>
    </div>
@endsection
