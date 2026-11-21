@extends('layouts.admin.app')

@section('title', translate('Insurance & Trade Protection'))

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title mr-3">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/trade-insurance-badge.svg') }}" class="w--26" alt="Insurance">
                </span>
                <span>
                    {{ translate('messages.business_setup') }} - {{ translate('Crop & Cross-Border Insurance') }}
                </span>
            </h1>
            @include('admin-views.business-settings.partials.nav-menu')
        </div>
        <!-- End Page Header -->

        @php
            $insurance_status = \App\Models\BusinessSetting::where('key', 'insurance_status')->first()?->value ?? 0;
            $insurance_rate_percent = \App\Models\BusinessSetting::where('key', 'insurance_rate_percent')->first()?->value ?? 2.5;
            $insurance_min_fee = \App\Models\BusinessSetting::where('key', 'insurance_min_fee')->first()?->value ?? 0;
            $insurance_max_fee = \App\Models\BusinessSetting::where('key', 'insurance_max_fee')->first()?->value ?? 0;
            $insurance_cross_border_auto = \App\Models\BusinessSetting::where('key', 'insurance_cross_border_auto')->first()?->value ?? 1;
            $insurance_allow_opt_in = \App\Models\BusinessSetting::where('key', 'insurance_allow_opt_in')->first()?->value ?? 1;
            $insurance_show_badge = \App\Models\BusinessSetting::where('key', 'insurance_show_badge')->first()?->value ?? 1;
            $insurance_crop_spoilage = \App\Models\BusinessSetting::where('key', 'insurance_crop_spoilage')->first()?->value ?? 1;
            $insurance_cross_border_transit = \App\Models\BusinessSetting::where('key', 'insurance_cross_border_transit')->first()?->value ?? 1;
            $insurance_quality_escrow = \App\Models\BusinessSetting::where('key', 'insurance_quality_escrow')->first()?->value ?? 1;
            $insurance_provider_name = \App\Models\BusinessSetting::where('key', 'insurance_provider_name')->first()?->value ?? 'Aduanefie Trade & Crop Assurance';
            $insurance_policy_summary = \App\Models\BusinessSetting::where('key', 'insurance_policy_summary')->first()?->value ?? 'Protects crop harvests, cross-border transit, and order quality under Aduanefie Escrow.';
            $esperworks_api_key = \App\Models\BusinessSetting::where('key', 'esperworks_api_key')->first()?->value ?? '';
        @endphp

        <form action="{{ route('admin.business-settings.update-insurance') }}" method="post">
            @csrf
            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h5 class="card-title d-flex align-items-center gap-2">
                                <img src="{{ asset('public/assets/admin/img/trade-insurance-badge.svg') }}" width="22" height="22" alt="">
                                <span>{{ translate('Insurance Program Controls') }}</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                            <span class="pr-2">{{ translate('Enable Crop & Trade Insurance') }}</span>
                                            <input type="checkbox" class="toggle-switch-input" name="insurance_status" value="1" {{ $insurance_status == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                            <span class="pr-2">{{ translate('Show Insured Badge on Products') }}</span>
                                            <input type="checkbox" class="toggle-switch-input" name="insurance_show_badge" value="1" {{ $insurance_show_badge == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                            <span class="pr-2">{{ translate('Auto-Apply on Cross-Border Orders') }}</span>
                                            <input type="checkbox" class="toggle-switch-input" name="insurance_cross_border_auto" value="1" {{ $insurance_cross_border_auto == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group mb-0">
                                        <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                            <span class="pr-2">{{ translate('Allow Buyer Opt-In at Checkout') }}</span>
                                            <input type="checkbox" class="toggle-switch-input" name="insurance_allow_opt_in" value="1" {{ $insurance_allow_opt_in == 1 ? 'checked' : '' }}>
                                            <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Premium Rate (%)') }}</label>
                                    <input type="number" step="0.1" min="0" max="100" name="insurance_rate_percent" class="form-control" value="{{ $insurance_rate_percent }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Minimum Fee') }}</label>
                                    <input type="number" step="0.01" min="0" name="insurance_min_fee" class="form-control" value="{{ $insurance_min_fee }}">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">{{ translate('Maximum Fee Cap (0 = no cap)') }}</label>
                                    <input type="number" step="0.01" min="0" name="insurance_max_fee" class="form-control" value="{{ $insurance_max_fee }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate('Assurance Provider / Program Name') }}</label>
                                    <input type="text" name="insurance_provider_name" class="form-control" value="{{ $insurance_provider_name }}">
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate('Buyer Policy Summary') }}</label>
                                    <textarea name="insurance_policy_summary" rows="3" class="form-control">{{ $insurance_policy_summary }}</textarea>
                                </div>

                                <div class="col-12">
                                    <label class="form-label">{{ translate('EsperWorks API Key (tryesperworks.com - Invoices & Contracts e-Sign)') }}</label>
                                    <input type="password" name="esperworks_api_key" class="form-control" value="{{ $esperworks_api_key }}" placeholder="ew_live_...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">{{ translate('Coverage Pillars') }}</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                        <span class="pr-2">{{ translate('Crop & Spoilage') }}</span>
                                        <input type="checkbox" class="toggle-switch-input" name="insurance_crop_spoilage" value="1" {{ $insurance_crop_spoilage == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                        <span class="pr-2">{{ translate('Cross-Border Transit') }}</span>
                                        <input type="checkbox" class="toggle-switch-input" name="insurance_cross_border_transit" value="1" {{ $insurance_cross_border_transit == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <label class="toggle-switch toggle-switch-sm d-flex justify-content-between border rounded px-3 form-control">
                                        <span class="pr-2">{{ translate('Quality & Escrow') }}</span>
                                        <input type="checkbox" class="toggle-switch-input" name="insurance_quality_escrow" value="1" {{ $insurance_quality_escrow == 1 ? 'checked' : '' }}>
                                        <span class="toggle-switch-label"><span class="toggle-switch-indicator"></span></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">{{ translate('Marketplace Badge Preview') }}</h5>
                        </div>
                        <div class="card-body text-center">
                            <div class="p-3 rounded border mb-3" style="background: linear-gradient(135deg, #FFFBEB 0%, #F0FDF4 100%); border-color: #F59E0B !important;">
                                <img src="{{ asset('public/assets/admin/img/trade-insurance-badge.svg') }}" width="56" height="56" alt="Trade Insurance Badge" class="mb-2">
                                <h6 class="font-weight-bold mb-1" style="color: #92400E;">{{ $insurance_provider_name }}</h6>
                                <p class="small text-muted mb-2">{{ $insurance_policy_summary }}</p>
                                <span class="badge badge-soft-warning px-2 py-1">
                                    {{ translate('Insured') }} · {{ $insurance_rate_percent }}%
                                </span>
                            </div>
                            <p class="small text-muted text-left mb-0">
                                {{ translate('Products marked as Insured in Product Setup will automatically display this Trade Assurance shield badge on the marketplace, product details, and checkout.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="btn--container justify-content-end mt-4">
                <button type="reset" class="btn btn--reset">{{ translate('messages.reset') }}</button>
                <button type="submit" class="btn btn--primary">{{ translate('messages.save_information') }}</button>
            </div>
        </form>
    </div>
@endsection
