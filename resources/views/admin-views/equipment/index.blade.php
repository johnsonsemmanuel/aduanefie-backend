@extends('layouts.admin.app')

@section('title', translate('Equipment'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endpush

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <i class="tio-shop"></i>
                        </span>
                        <span>
                            {{ translate('Equipment') }}
                            <span class="badge badge-soft-dark ml-2">{{ $equipment->total() }}</span>
                        </span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn-sm btn--primary" href="{{ route('admin.equipment.create') }}">
                        <i class="tio-add"></i> {{ translate('Add New Equipment') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2 mb-3">
            <ul class="nav nav-tabs border-0 nav--tabs">
                <li class="nav-item">
                    <a class="nav-link {{ $status === null ? 'active' : '' }}" href="{{ route('admin.equipment.index') }}">
                        {{ translate('All') }}
                    </a>
                </li>
                @foreach(['available', 'maintenance', 'retired'] as $equipmentStatus)
                    <li class="nav-item">
                        <a class="nav-link {{ $status === $equipmentStatus ? 'active' : '' }}"
                           href="{{ route('admin.equipment.index', ['status' => $equipmentStatus]) }}">
                            {{ translate(ucfirst($equipmentStatus)) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <div class="card-header py-2 border-0">
                <form class="search-form d-flex align-items-center gap-2 flex-wrap">
                    <select name="store_id" class="form-control js-select2-custom min--200" onchange="this.form.submit()">
                        <option value="">{{ translate('All Stores') }}</option>
                        @foreach ($stores as $store)
                            <option value="{{ $store->id }}" {{ $storeId == $store->id ? 'selected' : '' }}>{{ $store->name }}</option>
                        @endforeach
                    </select>
                    <div class="input-group input--group min--260">
                        <input type="search" name="search" value="{{ $search }}" class="form-control h--40px"
                               placeholder="{{ translate('Search by name') }}" aria-label="{{ translate('Search') }}">
                        <button type="submit" class="btn btn--secondary"><i class="tio-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('#') }}</th>
                                <th class="table-column-pl-0 border-0">{{ translate('Equipment') }}</th>
                                <th class="border-0">{{ translate('Store') }}</th>
                                <th class="border-0">{{ translate('Rates') }}</th>
                                <th class="border-0">{{ translate('Stock') }}</th>
                                <th class="border-0">{{ translate('Operator') }}</th>
                                <th class="border-0">{{ translate('Status') }}</th>
                                <th class="border-0">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($equipment as $key => $item)
                                @php
                                    $badgeClass = match ($item->status) {
                                        'available' => 'success',
                                        'maintenance' => 'warning',
                                        'retired' => 'secondary',
                                        default => 'dark',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $key + $equipment->firstItem() }}</td>
                                    <td class="table-column-pl-0">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="{{ $item->item?->image_full_url }}" alt="equipment" class="rounded" width="48" height="48">
                                            <a href="{{ route('admin.equipment.edit', $item->id) }}" class="text-dark font-weight-semibold">
                                                {{ $item->item?->name }}
                                            </a>
                                        </div>
                                    </td>
                                    <td>{{ $item->item?->store?->name }}</td>
                                    <td>
                                        <div class="small">
                                            @if ($item->hourly_rate)<div>{{ translate('Hourly') }}: {{ \App\CentralLogics\Helpers::format_currency($item->hourly_rate) }}</div>@endif
                                            @if ($item->daily_rate)<div>{{ translate('Daily') }}: {{ \App\CentralLogics\Helpers::format_currency($item->daily_rate) }}</div>@endif
                                            @if ($item->weekly_rate)<div>{{ translate('Weekly') }}: {{ \App\CentralLogics\Helpers::format_currency($item->weekly_rate) }}</div>@endif
                                            @if ($item->monthly_rate)<div>{{ translate('Monthly') }}: {{ \App\CentralLogics\Helpers::format_currency($item->monthly_rate) }}</div>@endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-dark">{{ $item->item?->stock }}</span>
                                    </td>
                                    <td>
                                        {{ $item->operator_included ? translate('Yes') : translate('No') }}
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $badgeClass }}">{{ translate(ucfirst($item->status)) }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1">
                                            @if ($item->status === 'available')
                                                <a class="btn action-btn btn-outline-warning"
                                                   href="{{ route('admin.equipment.status', [$item->id, 'maintenance']) }}"
                                                   title="{{ translate('Mark Under Maintenance') }}">
                                                    <i class="tio-settings-outlined"></i>
                                                </a>
                                            @elseif ($item->status === 'maintenance')
                                                <a class="btn action-btn btn-outline-success"
                                                   href="{{ route('admin.equipment.status', [$item->id, 'available']) }}"
                                                   title="{{ translate('Mark Available') }}">
                                                    <i class="tio-checkmark-circle"></i>
                                                </a>
                                            @endif
                                            <a class="btn action-btn btn--warning btn-outline-warning"
                                               href="{{ route('admin.equipment.edit', $item->id) }}"
                                               title="{{ translate('Edit') }}">
                                                <i class="tio-edit"></i>
                                            </a>
                                            <a class="btn action-btn btn--danger btn-outline-danger"
                                               href="javascript:void(0)"
                                               onclick="route_alert('{{ route('admin.equipment.destroy', $item->id) }}', '{{ translate('Want to delete this equipment?') }}')"
                                               title="{{ translate('Delete') }}">
                                                <i class="tio-delete-outlined"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($equipment) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $equipment->withQueryString()->links() !!}
            </div>
            @if(count($equipment) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>{{ translate('No data found') }}</h5>
                </div>
            @endif
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";
        function route_alert(url, message) {
            Swal.fire({
                title: '{{ translate('Are you sure?') }}',
                text: message,
                type: 'warning',
                showCancelButton: true,
                cancelButtonColor: 'default',
                confirmButtonColor: '#FC6A57',
                cancelButtonText: '{{ translate('no') }}',
                confirmButtonText: '{{ translate('Yes') }}',
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    location.href = url;
                }
            });
        }
    </script>
@endpush
