@extends('marketer.layout')

@section('title', 'Register Farmer / Stakeholder')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}<br>
    <span class="small">{{ $marketer->email }}</span>
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'register'])
@endsection

@section('content')
    <div class="card">
        <h1>Register a Farmer or Agro-Stakeholder</h1>
        <p class="muted small" style="margin-top:0;">
            Every farm or cooperative you register here is automatically credited to your referral code
            <strong>{{ $marketer->marketer_code ?: ('MKT-GH-' . $marketer->id) }}</strong>
            and queued in Admin for verification &amp; onboarding bounty.
        </p>

        <form method="post" action="{{ route('marketer.register-farmer.store') }}">
            @csrf
            <div class="grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                <div class="field" style="margin-bottom:0;">
                    <label for="farm_name">Farm / Cooperative / Business Name *</label>
                    <input type="text" name="farm_name" id="farm_name" value="{{ old('farm_name') }}" required placeholder="e.g. Ejura Maize Growers">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="farm_type">Stakeholder Type *</label>
                    <select name="farm_type" id="farm_type" required>
                        <option value="commercial" {{ old('farm_type') === 'commercial' ? 'selected' : '' }}>Commercial Farm</option>
                        <option value="cooperative" {{ old('farm_type') === 'cooperative' ? 'selected' : '' }}>Farmer Cooperative</option>
                        <option value="backyard" {{ old('farm_type') === 'backyard' ? 'selected' : '' }}>Backyard Grower</option>
                        <option value="processor" {{ old('farm_type') === 'processor' ? 'selected' : '' }}>Agro Processor / Aggregator</option>
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="owner_f_name">Owner First Name *</label>
                    <input type="text" name="owner_f_name" id="owner_f_name" value="{{ old('owner_f_name') }}" required placeholder="e.g. Kwame">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="owner_l_name">Owner Last Name</label>
                    <input type="text" name="owner_l_name" id="owner_l_name" value="{{ old('owner_l_name') }}" placeholder="e.g. Boateng">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" name="phone" id="phone" value="{{ old('phone') }}" required placeholder="e.g. 0244123456">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="email">Email Address *</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required placeholder="farmer@example.com">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="region">Region *</label>
                    <select name="region" id="region" required>
                        <option value="">Select Region</option>
                        @foreach($regions as $r)
                            <option value="{{ $r }}" {{ (old('region') ?: $marketer->marketer_region) === $r ? 'selected' : '' }}>{{ $r }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="district">District / Community</label>
                    <input type="text" name="district" id="district" value="{{ old('district') }}" placeholder="e.g. Techiman North">
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="zone_id">Marketplace Zone *</label>
                    <select name="zone_id" id="zone_id" required>
                        @foreach($zones as $z)
                            <option value="{{ $z->id }}" {{ old('zone_id') == $z->id ? 'selected' : '' }}>{{ $z->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field" style="margin-bottom:0;">
                    <label for="module_id">Marketplace Category *</label>
                    <select name="module_id" id="module_id" required>
                        @foreach($modules as $mod)
                            <option value="{{ $mod->id }}" {{ old('module_id') == $mod->id ? 'selected' : '' }}>{{ $mod->module_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="field" style="margin-top:14px;">
                <label for="address">Farm / Collection Address *</label>
                <input type="text" name="address" id="address" value="{{ old('address') }}" required placeholder="Town, landmark, or farm gate location">
            </div>

            <div class="field">
                <label for="primary_crops">Primary Crops / Products</label>
                <input type="text" name="primary_crops" id="primary_crops" value="{{ old('primary_crops') }}" placeholder="e.g. White Maize, Pona Yam, Fresh Tomatoes">
            </div>

            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button type="submit">Submit Farmer Registration</button>
                <a href="{{ route('marketer.dashboard') }}" class="btn secondary">Cancel</a>
            </div>
        </form>
    </div>
@endsection
