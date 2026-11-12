@extends('layouts.admin.app')

@section('title', translate('messages.farming_calendar'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center py-2">
                <div class="col-sm mb-2 mb-sm-0">
                    <div class="d-flex align-items-center">
                        <img src="{{asset('/public/assets/admin/img/farming.svg')}}" class="w--22" alt="farming">
                        <div class="w-0 flex-grow pl-2">
                            <h1 class="page-header-title mb-0">{{translate('messages.farming_calendar')}}</h1>
                            <p class="page-header-text m-0">{{translate('messages.seasonal_planting_harvest_planning')}}</p>
                        </div>
                    </div>
                </div>
                <div class="col-sm-auto min--280">
                    <select name="zone_id" class="form-control js-select2-custom fetch_data_zone_wise">
                        <option value="all">{{ translate('messages.All_Zones') }}</option>
                        @foreach($zones as $zone)
                            <option value="{{$zone['id']}}" {{ $zone_id == $zone['id'] ? 'selected' : '' }}>{{$zone['name']}}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
        <!-- End Page Header -->

        <!-- Farming Calendar Cards by Month -->
        @if(count($months) > 0)
            <div class="row g-3">
                @foreach($months as $monthName => $monthItems)
                    <div class="col-lg-4 col-xl-3">
                        <div class="card farming-month-card h-100 border-{{ $loop->first ? 'primary' : 'secondary' }} shadow-sm">
                            <div class="card-header bg-{{ $loop->first ? 'primary' : 'light' }} text-white">
                                <h6 class="mb-0">{{ $monthName }}</h6>
                                <span class="badge badge-light">{{ count($monthItems) }} {{ translate('messages.items') }}</span>
                            </div>
                            <div class="card-body p-3">
                                <div class="farming-items-list">
                                    @foreach($monthItems as $item)
                                        <div class="d-flex align-items-start mb-3 farming-item-row">
                                            <div class="flex-shrink-0">
                                                <img src="{{ $item->image_full_url ?? asset('/public/assets/admin/img/160x160/img2.jpg') }}"
                                                    class="rounded-circle farming-item-avatar" width="40" height="40" alt="{{$item->name}}">
                                            </div>
                                            <div class="flex-grow-1 ms-3">
                                                <h6 class="mb-1">{{ Str::limit($item->name, 25) }}</h6>
                                                <span class="badge badge-soft-{{ $item->farming_status === 'harvest_ready' ? 'success' : 'warning' }}">{{ ucfirst($item->farming_status) }}</span>
                                                @if($item->is_backyard)
                                                    <span class="badge badge-soft-info ms-1">{{ translate('messages.backyard_grown') }}</span>
                                                @endif
                                                <div class="mt-1 text-muted small">
                                                    {{ \App\CentralLogics\Helpers::format_currency($item->price) }}
                                                    @if($item->store)
                                                        • <a href="{{route('admin.store.view', $item->store->id)}}" class="text-decoration-none">{{ Str::limit($item->store->name, 15) }}</a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                @if(count($monthItems) > 5)
                                    <div class="text-center mt-2">
                                        <a href="{{route('admin.ongoing_farming')}}" class="btn btn--outline-primary btn-sm">{{translate('messages.view_all')}}</a>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="empty--data text-center py-5">
                <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                <h5>{{translate('no_data_found')}}</h5>
                <p class="text-muted">{{translate('messages.no_farming_scheduled')}}</p>
            </div>
        @endif
    </div>
@endsection

@push('css_or_js')
<style>
    .farming-month-card {
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s ease;
    }
    .farming-month-card:hover {
        transform: translateY(-4px);
    }
    .farming-item-avatar {
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .farming-item-row {
        border-bottom: 1px solid #f0f0f0;
        padding-bottom: 8px;
    }
    .farming-item-row:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }
    .badge-soft-warning {
        background-color: #fff3cd;
        color: #856404;
    }
    .badge-soft-success {
        background-color: #d4edda;
        color: #155724;
    }
    .badge-soft-info {
        background-color: #cce5ff;
        color: #004085;
    }
</style>
@endpush
