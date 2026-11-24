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
                <label for="country">Country</label>
                <select name="country" id="country" required>
                    <option value="">Select a country</option>
                    @foreach($countries as $code => $name)
                        <option value="{{ $code }}" {{ old('country') === $code ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="network">Mobile money network</label>
                <select name="network" id="network" required>
                    <option value="">Select a network</option>
                    @foreach($networksByCountry as $countryCode => $networks)
                        @foreach($networks as $networkKey => $label)
                            <option value="{{ $networkKey }}" data-country="{{ $countryCode }}"
                                {{ old('network') === $networkKey ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label for="account_name">Account name</label>
                <input type="text" name="account_name" id="account_name" value="{{ old('account_name') }}"
                       required maxlength="100">
            </div>

            <div class="field">
                <label for="account_number">Account number</label>
                <input type="tel" name="account_number" id="account_number" value="{{ old('account_number', $marketer->phone) }}"
                       required maxlength="50">
            </div>

            <div class="field">
                <label for="note">Note (optional)</label>
                <textarea name="note" id="note" rows="3" maxlength="500">{{ old('note') }}</textarea>
            </div>

            <div class="field">
                <label for="current_password">Confirm your account password</label>
                <input type="password" name="current_password" id="current_password" required autocomplete="current-password"
                       placeholder="Re-enter password to authorize payout request">
            </div>

            <button type="submit">Submit request</button>
            <a class="btn secondary" href="{{ route('marketer.payouts') }}">Cancel</a>
        </form>
    </div>

    <script>
        (function () {
            const country = document.getElementById('country');
            const network = document.getElementById('network');
            if (!country || !network) return;

            function filterNetworks() {
                for (const option of network.options) {
                    if (!option.dataset.country) continue;
                    const allowed = option.dataset.country === country.value;
                    option.disabled = !allowed;
                    option.hidden = !allowed;
                    if (!allowed && option.selected) option.selected = false;
                }
            }

            country.addEventListener('change', filterNetworks);
            filterNetworks();
        })();
    </script>
@endsection
