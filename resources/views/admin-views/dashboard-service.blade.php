@extends('layouts.admin.app')

@section('title', \App\Models\BusinessSetting::where(['key' => 'business_name'])->first()->value ?? translate('messages.dashboard'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        @if(auth('admin')->user()->role_id == 1)
        @php($mod = \App\Models\Module::find(Config::get('module.current_module_id')))
        <div class="page-header">
            <div class="row align-items-center py-2">
                <div class="col-sm mb-2 mb-sm-0">
                    <div class="d-flex align-items-center">
                        <img class="onerror-image" data-onerror-image="{{ asset('/public/assets/admin/img/eshop.svg') }}" src="{{ $mod->icon_full_url }}" width="38" alt="img">
                        <div class="w-0 flex-grow pl-2">
                            <h1 class="page-header-title mb-0">{{ translate($mod->module_name) }} {{ translate('Service Hub') }}.</h1>
                            <p class="page-header-text m-0">{{ translate('Manage agro-service providers, mechanization listings, and farmer booking requests.') }}</p>
                        </div>
                    </div>
                </div>

                <div class="col-sm-auto min--280">
                    <select name="zone_id" class="form-control js-select2-custom fetch_data_zone_wise">
                        <option value="all">{{ translate('messages.All_Zones') }}</option>
                        @foreach(\App\Models\Zone::orderBy('name')->get() as $zone)
                            <option value="{{ $zone['id'] }}" {{ $params['zone_id'] == $zone['id'] ? 'selected' : '' }}>
                                {{ $zone['name'] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-body pt-0">
                <div class="d-flex flex-wrap align-items-center justify-content-end">
                    <div class="status-filter-wrap">
                        <div class="statistics-btn-grp">
                            <label>
                                <input type="radio" name="statistics" class="order_stats_update" value="this_year" {{ $params['statistics_type'] == 'this_year' ? 'checked' : '' }} hidden>
                                <span>{{ translate('This_Year') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="statistics" class="order_stats_update" value="this_month" {{ $params['statistics_type'] == 'this_month' ? 'checked' : '' }} hidden>
                                <span>{{ translate('This_Month') }}</span>
                            </label>
                            <label>
                                <input type="radio" name="statistics" class="order_stats_update" value="this_week" {{ $params['statistics_type'] == 'this_week' ? 'checked' : '' }} hidden>
                                <span>{{ translate('This_Week') }}</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="row g-2" id="order_stats">
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{ asset('/public/assets/admin/img/dashboard/stats/products.svg') }}" alt="dashboard/stats">
                            <h6 class="name">{{ translate('Active Services') }}</h6>
                            <h3 class="count">{{ $data['total_items'] }}</h3>
                            <div class="subtxt">{{ $data['new_items'] }} {{ translate('newly added') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{ asset('/public/assets/admin/img/dashboard/stats/orders.svg') }}" alt="dashboard/stats">
                            <h6 class="name">{{ translate('Service Bookings') }}</h6>
                            <h3 class="count">{{ $data['total_orders'] }}</h3>
                            <div class="subtxt">{{ $data['new_orders'] }} {{ translate('newly requested') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{ asset('/public/assets/admin/img/dashboard/stats/stores.svg') }}" alt="dashboard/stats">
                            <h6 class="name">{{ translate('Service Providers') }}</h6>
                            <h3 class="count">{{ $data['total_stores'] }}</h3>
                            <div class="subtxt">{{ $data['new_stores'] }} {{ translate('newly onboarded') }}</div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-3">
                        <div class="__dashboard-card-2">
                            <img src="{{ asset('/public/assets/admin/img/dashboard/stats/customers.svg') }}" alt="dashboard/stats">
                            <h6 class="name">{{ translate('Clients & Farmers') }}</h6>
                            <h3 class="count">{{ $data['total_customers'] }}</h3>
                            <div class="subtxt">{{ $data['new_customers'] }} {{ translate('newly joined') }}</div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="row g-2">
                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{ route('admin.order.list', ['pending']) }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                            <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/unassigned.svg') }}" alt="dashboard" class="oder--card-icon">
                                            <span>{{ translate('Pending Requests') }}</span>
                                        </h6>
                                        <span class="card-title text-3F8CE8">{{ $data['searching_for_dm'] }}</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{ route('admin.order.list', ['processing']) }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                            <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/packaging.svg') }}" alt="dashboard" class="oder--card-icon">
                                            <span>{{ translate('Jobs In Progress') }}</span>
                                        </h6>
                                        <span class="card-title text-FFA800">{{ $data['preparing_in_rs'] }}</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{ route('admin.order.list', ['delivered']) }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                            <img src="{{ asset('/public/assets/admin/img/dashboard/grocery/delivered.svg') }}" alt="dashboard" class="oder--card-icon">
                                            <span>{{ translate('Completed Jobs') }}</span>
                                        </h6>
                                        <span class="card-title text-success">{{ $data['delivered'] }}</span>
                                    </div>
                                </a>
                            </div>
                            <div class="col-sm-6 col-lg-3">
                                <a class="order--card h-100" href="{{ route('admin.order.list', ['canceled']) }}">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h6 class="card-subtitle d-flex justify-content-between m-0 align-items-center">
                                            <img src="{{ asset('/public/assets/admin/img/order-status/canceled.svg') }}" alt="dashboard" class="oder--card-icon">
                                            <span>{{ translate('Canceled Bookings') }}</span>
                                        </h6>
                                        <span class="card-title text-danger">{{ $data['canceled'] }}</span>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-2">
            <div class="col-lg-8 col--xl-8">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex flex-wrap justify-content-between align-items-center __gap-12px">
                            <div class="__gross-amount" id="gross_sale">
                                <h6>{{ \App\CentralLogics\Helpers::format_currency(array_sum($total_sell)) }}</h6>
                                <span>{{ translate('Service Revenue') }}</span>
                            </div>
                            <select class="custom-select border-0 text-center w-auto ml-auto commission_overview_stats_update" name="commission_overview">
                                <option value="this_year" {{ $params['commission_overview'] == 'this_year' ? 'selected' : '' }}>{{ translate('This year') }}</option>
                                <option value="this_month" {{ $params['commission_overview'] == 'this_month' ? 'selected' : '' }}>{{ translate('This month') }}</option>
                                <option value="this_week" {{ $params['commission_overview'] == 'this_week' ? 'selected' : '' }}>{{ translate('This week') }}</option>
                            </select>
                        </div>
                        <div id="commission-overview-board">
                            <div id="grow-sale-chart"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col--xl-4">
                <div class="card h-100">
                    <div class="card-header border-0">
                        <h5 class="card-header-title">{{ translate('User Statistics') }}</h5>
                        <div id="stat_zone">@include('admin-views.partials._zone-change', ['data' => $data])</div>
                    </div>
                    <div class="card-body" id="user-overview-board">
                        <div class="position-relative pie-chart">
                            <div id="dognut-pie"></div>
                            <div class="total--orders">
                                <h3 class="text-uppercase mb-xxl-2">{{ $data['customer'] + $data['stores'] + $data['delivery_man'] }}</h3>
                                <span class="text-capitalize">{{ translate('messages.total_users') }}</span>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap justify-content-center mt-4">
                            <div class="chart--label"><span class="indicator chart-bg-1"></span><span class="info">{{ translate('messages.customer') }} {{ $data['customer'] }}</span></div>
                            <div class="chart--label"><span class="indicator chart-bg-2"></span><span class="info">{{ translate('Providers') }} {{ $data['stores'] }}</span></div>
                            <div class="chart--label"><span class="indicator chart-bg-3"></span><span class="info">{{ translate('Field Crew') }} {{ $data['delivery_man'] }}</span></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-6">
                <div class="card h-100" id="top-restaurants-view">
                    @include('admin-views.partials._top-restaurants', ['top_restaurants' => $data['top_restaurants']])
                </div>
            </div>
            <div class="col-lg-6 col-md-6">
                <div class="card h-100" id="top-selling-foods-view">
                    @include('admin-views.partials._top-selling-foods', ['top_sell' => $data['top_sell']])
                </div>
            </div>
        </div>
        @else
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">{{ translate('messages.welcome') }}, {{ auth('admin')->user()->f_name }}.</h1>
                    <p class="page-header-text">{{ translate('messages.employee_welcome_message') }}</p>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection

@push('script')
    <script src="{{ asset('/public/assets/admin/js/apex-charts/apexcharts.js') }}"></script>
@endpush

@push('script_2')
    <script>
        "use strict";
        let options = {
            series: [{{ $data['customer'] }}, {{ $data['stores'] }}, {{ $data['delivery_man'] }}],
            chart: { width: 320, type: 'donut' },
            labels: ['{{ translate('Customer') }}', '{{ translate('Providers') }}', '{{ translate('Field Crew') }}'],
            dataLabels: { enabled: false, style: { colors: ['#4F3E2C', '#8B9F69', '#C7BA9E'] } },
            responsive: [{ breakpoint: 1650, options: { chart: { width: 250 } } }],
            colors: ['#4F3E2C', '#8B9F69', '#C7BA9E'],
            fill: { colors: ['#4F3E2C', '#8B9F69', '#C7BA9E'] },
            legend: { show: false }
        };
        new ApexCharts(document.querySelector("#dognut-pie"), options).render();

        options = {
            series: [
                { name: '{{ translate('Gross Booking Value') }}', data: [{{ implode(",", $total_sell) }}] },
                { name: '{{ translate('Admin Commission') }}', data: [{{ implode(",", $commission) }}] },
                { name: '{{ translate('Dispatch Fee') }}', data: [{{ implode(",", $delivery_commission) }}] }
            ],
            chart: { height: 350, type: 'area', toolbar: { show: false }, colors: ['#8B9F69', '#C98A5B', '#4F3E2C'] },
            colors: ['#8B9F69', '#C98A5B', '#4F3E2C'],
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2, colors: ['#8B9F69', '#C98A5B', '#4F3E2C'] },
            fill: { type: 'solid', opacity: 0.16, colors: ['#8B9F69', '#C98A5B', '#4F3E2C'] },
            xaxis: { categories: [{!! implode(",", $label) !!}] },
            tooltip: { x: { format: 'dd/MM/yy HH:mm' } }
        };
        new ApexCharts(document.querySelector("#grow-sale-chart"), options).render();

        $('.fetch_data_zone_wise').on('change', function () {
            const zone_id = $(this).val();
            $.post({
                url: '{{ route('admin.dashboard-stats.zone') }}',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                data: { zone_id },
                success: function () { window.location.search = '?zone_id=' + encodeURIComponent(zone_id); }
            });
        });
    </script>
@endpush
