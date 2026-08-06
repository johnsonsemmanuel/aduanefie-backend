@extends('layouts.admin.app')

@section('title', translate('Equipment Bookings'))

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
                            <i class="tio-shopping-basket"></i>
                        </span>
                        <span>
                            {{ translate('Equipment Bookings') }}
                            <span class="badge badge-soft-dark ml-2">{{ $bookings->total() }}</span>
                        </span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn-sm btn-white" href="{{ route('admin.equipment-bookings.index') }}">
                        <i class="tio-refresh"></i> {{ translate('Reset') }}
                    </a>
                </div>
            </div>
        </div>

        <div class="js-nav-scroller hs-nav-scroller-horizontal mt-2 mb-3">
            <ul class="nav nav-tabs border-0 nav--tabs">
                <li class="nav-item">
                    <a class="nav-link {{ $status === null ? 'active' : '' }}"
                       href="{{ route('admin.equipment-bookings.index') }}">
                        {{ translate('All') }}
                    </a>
                </li>
                @foreach(['pending', 'confirmed', 'active', 'overdue', 'completed', 'cancelled'] as $bookingStatus)
                    <li class="nav-item">
                        <a class="nav-link {{ $status === $bookingStatus ? 'active' : '' }}"
                           href="{{ route('admin.equipment-bookings.index', ['status' => $bookingStatus]) }}">
                            {{ translate(ucfirst($bookingStatus)) }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card">
            <div class="card-body p-0">
                <div class="table-responsive datatable-custom">
                    <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                        <thead class="thead-light">
                            <tr>
                                <th class="border-0">{{ translate('#') }}</th>
                                <th class="table-column-pl-0 border-0">{{ translate('Equipment') }}</th>
                                <th class="border-0">{{ translate('Customer') }}</th>
                                <th class="border-0">{{ translate('Store') }}</th>
                                <th class="border-0">{{ translate('Rental Period') }}</th>
                                <th class="border-0">{{ translate('Duration') }}</th>
                                <th class="border-0">{{ translate('Total') }}</th>
                                <th class="border-0">{{ translate('Status') }}</th>
                                <th class="border-0">{{ translate('Actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($bookings as $key => $booking)
                                @php
                                    $badgeClass = match ($booking->status) {
                                        'pending' => 'warning',
                                        'confirmed' => 'info',
                                        'active' => 'primary',
                                        'overdue' => 'danger',
                                        'completed' => 'success',
                                        'cancelled' => 'secondary',
                                        default => 'dark',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $key + $bookings->firstItem() }}</td>
                                    <td class="table-column-pl-0">
                                        <a class="text-dark font-weight-semibold" href="{{ route('admin.equipment-bookings.show', $booking->id) }}">
                                            {{ $booking->item?->name ?? translate('Unavailable') }}
                                        </a>
                                    </td>
                                    <td>
                                        @if ($booking->customer)
                                            {{ $booking->customer->f_name }} {{ $booking->customer->l_name }}
                                        @else
                                            {{ translate('Unavailable') }}
                                        @endif
                                    </td>
                                    <td>{{ $booking->store?->name ?? translate('Unavailable') }}</td>
                                    <td>
                                        <div>{{ \App\CentralLogics\Helpers::date_format($booking->start_date) }}</div>
                                        <div class="text-muted">{{ \App\CentralLogics\Helpers::time_format($booking->start_date) }} &mdash; {{ \App\CentralLogics\Helpers::time_format($booking->end_date) }}</div>
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-dark">{{ $booking->duration_value }} {{ $booking->duration_type }}</span>
                                    </td>
                                    <td>
                                        {{ \App\CentralLogics\Helpers::format_currency($booking->total_amount) }}
                                        @if ($booking->security_deposit > 0)
                                            <div class="text-muted small">{{ translate('Deposit') }}: {{ \App\CentralLogics\Helpers::format_currency($booking->security_deposit) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="badge badge-soft-{{ $badgeClass }}">{{ translate(ucfirst($booking->status)) }}</span>
                                    </td>
                                    <td>
                                        <a class="btn action-btn btn--warning btn-outline-warning"
                                           href="{{ route('admin.equipment-bookings.show', $booking->id) }}"
                                           title="{{ translate('View') }}">
                                            <i class="tio-visible-outlined"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @if(count($bookings) !== 0)
                <hr>
            @endif
            <div class="page-area">
                {!! $bookings->withQueryString()->links() !!}
            </div>
            @if(count($bookings) === 0)
                <div class="empty--data">
                    <img src="{{asset('/public/assets/admin/svg/illustrations/sorry.svg')}}" alt="public">
                    <h5>{{ translate('No data found') }}</h5>
                </div>
            @endif
        </div>
    </div>
@endsection
