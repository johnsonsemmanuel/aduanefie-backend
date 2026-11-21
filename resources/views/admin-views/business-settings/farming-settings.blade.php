@extends('layouts.admin.app')

@section('title', 'Farming & SMS Settings')

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{ asset('public/assets/admin/img/addon_setting.png') }}" class="w--26" alt="">
                </span>
                <span>Farming &amp; SMS Settings</span>
            </h1>
        </div>
        <!-- End Page Header -->

        <div class="row g-3">
            <!-- Farming Module General Settings -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title mb-0">General Farming Settings</h4>
                    </div>
                    <div class="card-body p-30">
                        <form action="{{route('admin.business-settings.update-farming-settings')}}" method="POST">
                            @csrf

                            <div class="row g-3">
                                <!-- Module Enabled -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-20">
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" name="farming_module_enabled" id="farming_module_enabled" {{ $settings['farming_module_enabled'] ? 'checked' : '' }}>
                                            <label class="custom-control-label" for="farming_module_enabled">Enable Farming Module</label>
                                        </div>
                                        <small class="form-text text-muted">Allow farms to list crops in cultivation and accept harvest pre-orders.</small>
                                    </div>
                                </div>

                                <!-- Default Farming Status -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-30">
                                        <label class="form-label">Default Farming Status *</label>
                                        <select class="form-control" name="default_farming_status">
                                            <option value="farming" {{ $settings['default_farming_status'] == 'farming' ? 'selected' : '' }}>Farming (Growing)</option>
                                            <option value="harvest_ready" {{ $settings['default_farming_status'] == 'harvest_ready' ? 'selected' : '' }}>Harvest Ready</option>
                                            <option value="in_stock" {{ $settings['default_farming_status'] == 'in_stock' ? 'selected' : '' }}>In Stock</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Default Crop Cycle Days -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-30">
                                        <label class="form-label">Default Crop Cycle (Days) *</label>
                                        <input type="number" name="default_crop_cycle_days" class="form-control" value="{{ $settings['default_crop_cycle_days'] }}" placeholder="e.g. 60">
                                    </div>
                                </div>

                                <!-- Pre-order Enabled -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" name="pre_order_enabled_by_default" id="pre_order_enabled_by_default" {{ $settings['pre_order_enabled_by_default'] ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="pre_order_enabled_by_default">Enable Pre-Order by Default</label>
                                        <small class="form-text text-muted">Automatically enable buyer pre-orders on new cultivation listings.</small>
                                    </div>
                                </div>

                                <!-- Pre-order Limit Default -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-30">
                                        <label class="form-label">Default Pre-Order Limit *</label>
                                        <input type="number" name="pre_order_limit_default" class="form-control" value="{{ $settings['pre_order_limit_default'] }}" placeholder="e.g. 10">
                                    </div>
                                </div>

                                <!-- Harvest Notification -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" name="harvest_notification_enabled" id="harvest_notification_enabled" {{ $settings['harvest_notification_enabled'] ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="harvest_notification_enabled">Harvest Ready Notifications</label>
                                        <small class="form-text text-muted">Notify pre-order buyers when crops reach harvest readiness.</small>
                                    </div>
                                </div>

                                <!-- Growth Notification -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" name="growth_notification_enabled" id="growth_notification_enabled" {{ $settings['growth_notification_enabled'] ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="growth_notification_enabled">Crop Growth Stage Updates</label>
                                        <small class="form-text text-muted">Send cultivation milestone updates to reserving customers.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn--primary">Update Farming Settings</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SMS Configuration -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
                        <h4 class="card-title mb-0">IntekHost SMS Settings</h4>
                        @if(isset($smsBalance) && isset($smsBalance['units']))
                            @php($lowCredits = $smsBalance['units'] < 100)
                            <span class="badge {{ $lowCredits ? 'badge-soft-warning' : 'badge-soft-success' }} px-3 py-2" style="font-size: 0.85rem;">
                                <i class="tio-sms mr-1"></i>
                                SMS Balance: <strong>{{ number_format($smsBalance['units']) }}</strong> credits
                                @if($lowCredits)
                                    — Low Balance
                                @endif
                            </span>
                        @endif
                    </div>
                    <div class="card-body p-30">
                        <form action="{{route('admin.business-settings.update-farming-settings')}}" method="POST" id="intekhost-form">
                            @csrf

                            <div class="row g-3">
                                <!-- API Key -->
                                <div class="col-md-6 col-lg-6">
                                    <div class="form-group mb-30">
                                        <label class="form-label">{{translate('messages.api_key')}} *</label>
                                        <div class="input-group">
                                            <input type="password" id="intekhost_api_key_input" name="intekhost_api_key" class="form-control" value="{{ $settings['intekhost_api_key'] }}" placeholder="{{translate('messages.api_key_placeholder')}}" autocomplete="off">
                                            <div class="input-group-append">
                                                <button type="button" class="btn btn-outline-secondary" onclick="(function(btn){var inp=document.getElementById('intekhost_api_key_input');if(!inp)return;var show=inp.type==='password';inp.type=show?'text':'password';btn.textContent=show?'Hide':'Show';})(this)">Show</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Sender ID -->
                                <div class="col-md-6 col-lg-6">
                                    <div class="form-group mb-30">
                                        <label class="form-label">Sender ID</label>
                                        <input type="text" name="intekhost_sender_id" class="form-control" value="{{ $settings['intekhost_sender_id'] }}" placeholder="e.g. 226 (Aduanefie)">
                                        <small class="form-text text-muted">Approved IntekHost numeric Sender ID (226 = Aduanefie).</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn--primary">Update SMS Settings</button>
                            </div>
                        </form>

                        <hr class="my-4">
                        <form action="{{route('admin.business-settings.test-intekhost-sms')}}" method="POST" class="row align-items-end">
                            @csrf
                            <div class="col-md-6 col-lg-5">
                                <label class="form-label mb-1">Send Test SMS</label>
                                <input type="tel" name="test_phone" class="form-control" placeholder="e.g. 0549172627 or +233549172627" required>
                            </div>
                            <div class="col-md-auto mt-2 mt-md-0">
                                <button type="submit" class="btn btn--secondary">Send Test SMS</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection