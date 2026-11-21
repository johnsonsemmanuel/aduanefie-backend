@extends('layouts.admin.app')

@section('title', translate('messages.farming_settings'))

@push('css_or_js')

@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-header-title">
                <span class="page-header-icon">
                    <img src="{{asset('public/assets/admin/img/farming.svg')}}" class="w--26" alt="">
                </span>
                <span>{{translate('messages.farming_settings')}}</span>
            </h1>
        </div>
        <!-- End Page Header -->

        <div class="row g-3">
            <!-- Farming Module General Settings -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{translate('messages.general_farming_settings')}}</h4>
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
                                            <label class="custom-control-label" for="farming_module_enabled">{{translate('messages.enable_farming_module')}}</label>
                                        </div>
                                        <small class="form-text text-muted">{{translate('messages.enable_farming_module_desc')}}</small>
                                    </div>
                                </div>

                                <!-- Default Farming Status -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-30">
                                        <label class="form-label">{{translate('messages.default_farming_status')}} *</label>
                                        <select class="form-control" name="default_farming_status">
                                            <option value="farming" {{ $settings['default_farming_status'] == 'farming' ? 'selected' : '' }}>{{translate('messages.farming')}}</option>
                                            <option value="harvest_ready" {{ $settings['default_farming_status'] == 'harvest_ready' ? 'selected' : '' }}>{{translate('messages.harvest_ready')}}</option>
                                            <option value="in_stock" {{ $settings['default_farming_status'] == 'in_stock' ? 'selected' : '' }}>{{translate('messages.in_stock')}}</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Default Crop Cycle Days -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-30">
                                        <label class="form-label">{{translate('messages.default_crop_cycle_days')}} *</label>
                                        <input type="number" name="default_crop_cycle_days" class="form-control" value="{{ $settings['default_crop_cycle_days'] }}" placeholder="{{translate('messages.default_crop_cycle_days_placeholder')}}">
                                    </div>
                                </div>

                                <!-- Pre-order Enabled -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" name="pre_order_enabled_by_default" id="pre_order_enabled_by_default" {{ $settings['pre_order_enabled_by_default'] ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="pre_order_enabled_by_default">{{translate('messages.enable_pre_order_by_default')}}</label>
                                        <small class="form-text text-muted">{{translate('messages.enable_pre_order_default_desc')}}</small>
                                    </div>
                                </div>

                                <!-- Pre-order Limit Default -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="form-group mb-30">
                                        <label class="form-label">{{translate('messages.default_pre_order_limit')}} *</label>
                                        <input type="number" name="pre_order_limit_default" class="form-control" value="{{ $settings['pre_order_limit_default'] }}" placeholder="{{translate('messages.default_pre_order_limit_placeholder')}}">
                                    </div>
                                </div>

                                <!-- Harvest Notification -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" name="harvest_notification_enabled" id="harvest_notification_enabled" {{ $settings['harvest_notification_enabled'] ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="harvest_notification_enabled">{{translate('messages.harvest_notification_enabled')}}</label>
                                        <small class="form-text text-muted">{{translate('messages.harvest_notification_desc')}}</small>
                                    </div>
                                </div>

                                <!-- Growth Notification -->
                                <div class="col-md-6 col-lg-4">
                                    <div class="custom-control custom-checkbox">
                                        <input class="custom-control-input" type="checkbox" name="growth_notification_enabled" id="growth_notification_enabled" {{ $settings['growth_notification_enabled'] ? 'checked' : '' }}>
                                        <label class="custom-control-label" for="growth_notification_enabled">{{translate('messages.growth_notification_enabled')}}</label>
                                        <small class="form-text text-muted">{{translate('messages.growth_notification_desc')}}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn--primary">{{translate('messages.update_settings')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- SMS Configuration -->
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4 class="card-title">{{translate('messages.integram_settings')}}</h4>
                    </div>
                    <div class="card-body p-30">
                        <form action="{{route('admin.business-settings.update-farming-settings')}}" method="POST" id="intekhost-form">
                            @csrf

                            <div class="row g-3">
                                <!-- API Key -->
                                <div class="col-md-6 col-lg-6">
                                    <div class="form-group mb-30">
                                        <label class="form-label">{{translate('messages.api_key')}} *</label>
                                        <input type="text" name="intekhost_api_key" class="form-control" value="{{ $settings['intekhost_api_key'] }}" placeholder="{{translate('messages.api_key_placeholder')}}">
                                    </div>
                                </div>

                                <!-- Sender ID -->
                                <div class="col-md-6 col-lg-6">
                                    <div class="form-group mb-30">
                                        <label class="form-label">{{translate('messages.sender_id')}}</label>
                                        <input type="text" name="intekhost_sender_id" class="form-control" value="{{ $settings['intekhost_sender_id'] }}" placeholder="{{translate('messages.sender_id_placeholder')}}">
                                        <small class="form-text text-muted">{{translate('messages.sender_id_optional_desc')}}</small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn--primary">{{translate('messages.update_settings')}}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection