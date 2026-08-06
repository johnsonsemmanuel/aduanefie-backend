@extends('layouts.vendor.app')

@section('title', translate('Equipment Booking Details'))

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
                            {{ translate('Equipment Booking Details') }}
                        </span>
                    </h1>
                </div>
                <div class="col-sm-auto">
                    <a class="btn btn-sm btn-white" href="{{ route('vendor.equipment-bookings.index') }}">
                        <i class="tio-back-from-editing"></i> {{ translate('Back') }}
                    </a>
                </div>
            </div>
        </div>

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

        <div class="row g-2">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title m-0">{{ translate('Booking Summary') }}</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('Status') }}</span>
                                <span class="badge badge-soft-{{ $badgeClass }}">{{ translate(ucfirst($booking->status)) }}</span>
                            </li>
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('Start') }}</span>
                                <span>{{ \App\CentralLogics\Helpers::date_format($booking->start_date) }} {{ \App\CentralLogics\Helpers::time_format($booking->start_date) }}</span>
                            </li>
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('End') }}</span>
                                <span>{{ \App\CentralLogics\Helpers::date_format($booking->end_date) }} {{ \App\CentralLogics\Helpers::time_format($booking->end_date) }}</span>
                            </li>
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('Duration') }}</span>
                                <span>{{ $booking->duration_value }} {{ $booking->duration_type }}</span>
                            </li>
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('Rental Amount') }}</span>
                                <span>{{ \App\CentralLogics\Helpers::format_currency($booking->total_amount) }}</span>
                            </li>
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('Security Deposit') }}</span>
                                <span>{{ \App\CentralLogics\Helpers::format_currency($booking->security_deposit) }}</span>
                            </li>
                            <li class="d-flex justify-content-between mb-3">
                                <span class="text-muted">{{ translate('Operator Included') }}</span>
                                <span>{{ $booking->operator_included ? translate('Yes') : translate('No') }}</span>
                            </li>
                            @if ($booking->operator_included)
                                <li class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">{{ translate('Operator Fee') }}</span>
                                    <span>{{ \App\CentralLogics\Helpers::format_currency($booking->operator_fee) }}</span>
                                </li>
                            @endif
                            @if ($booking->order_id)
                                <li class="d-flex justify-content-between mb-3">
                                    <span class="text-muted">{{ translate('Order') }}</span>
                                    <span>#{{ $booking->order_id }}</span>
                                </li>
                            @endif
                            @if ($booking->notes)
                                <li>
                                    <div class="text-muted mb-1">{{ translate('Notes') }}</div>
                                    <p class="mb-0">{{ $booking->notes }}</p>
                                </li>
                            @endif
                        </ul>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title m-0">{{ translate('Equipment') }}</h5>
                    </div>
                    <div class="card-body">
                        @if ($booking->item)
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <img src="{{ $booking->item->image_full_url }}" alt="equipment" class="rounded" width="80" height="80">
                                <div>
                                    <div class="font-weight-semibold">{{ $booking->item->name }}</div>
                                    <div class="text-muted">{{ translate('Code') }}: {{ $booking->item->code }}</div>
                                </div>
                            </div>
                        @endif
                        @if ($booking->item?->equipment)
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Condition Notes') }}</span>
                                    <span>{{ $booking->item->equipment->condition_notes ?? '—' }}</span>
                                </li>
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Hourly Rate') }}</span>
                                    <span>{{ $booking->item->equipment->hourly_rate ? \App\CentralLogics\Helpers::format_currency($booking->item->equipment->hourly_rate) : '—' }}</span>
                                </li>
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Daily Rate') }}</span>
                                    <span>{{ $booking->item->equipment->daily_rate ? \App\CentralLogics\Helpers::format_currency($booking->item->equipment->daily_rate) : '—' }}</span>
                                </li>
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Weekly Rate') }}</span>
                                    <span>{{ $booking->item->equipment->weekly_rate ? \App\CentralLogics\Helpers::format_currency($booking->item->equipment->weekly_rate) : '—' }}</span>
                                </li>
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Monthly Rate') }}</span>
                                    <span>{{ $booking->item->equipment->monthly_rate ? \App\CentralLogics\Helpers::format_currency($booking->item->equipment->monthly_rate) : '—' }}</span>
                                </li>
                            </ul>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title m-0">{{ translate('Customer') }}</h5>
                    </div>
                    <div class="card-body">
                        @if ($booking->customer)
                            <ul class="list-unstyled mb-0">
                                <li class="d-flex align-items-center gap-2 mb-3">
                                    <img src="{{ $booking->customer->image_full_url }}" alt="customer" class="rounded-circle" width="48" height="48">
                                    <div>
                                        <div class="font-weight-semibold">{{ $booking->customer->f_name }} {{ $booking->customer->l_name }}</div>
                                        <div class="text-muted small">{{ translate('ID') }}: {{ $booking->customer->id }}</div>
                                    </div>
                                </li>
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Phone') }}</span>
                                    <a href="tel:{{ $booking->customer->phone }}">{{ $booking->customer->phone }}</a>
                                </li>
                                <li class="d-flex justify-content-between mb-2">
                                    <span class="text-muted">{{ translate('Email') }}</span>
                                    <a href="mailto:{{ $booking->customer->email }}">{{ $booking->customer->email }}</a>
                                </li>
                            </ul>
                        @else
                            <p class="mb-0">{{ translate('Unavailable') }}</p>
                        @endif
                    </div>
                </div>
            </div>

            @if (count($booking->conditionReports) > 0)
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title m-0">{{ translate('Condition Reports') }}</h5>
                        </div>
                        <div class="card-body">
                            @foreach ($booking->conditionReports as $report)
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span class="badge badge-soft-info">{{ translate(ucfirst(str_replace('_', ' ', $report->report_type))) }}</span>
                                        <span class="text-muted small">{{ \App\CentralLogics\Helpers::date_format($report->created_at) }}</span>
                                    </div>
                                    <div class="mb-2">
                                        <span class="text-muted">{{ translate('Reported by') }}:</span>
                                        <span>{{ translate(ucfirst($report->reported_by)) }}</span>
                                    </div>
                                    <div class="mb-2">
                                        <span class="text-muted">{{ translate('Condition') }}:</span>
                                        <span>{{ $report->condition_rating }}/5</span>
                                    </div>
                                    @if ($report->notes)
                                        <p class="mb-0 small">{{ $report->notes }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            @if (count($booking->extraCharges) > 0)
                <div class="col-lg-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title m-0">{{ translate('Extra Charges') }}</h5>
                        </div>
                        <div class="card-body">
                            @foreach ($booking->extraCharges as $charge)
                                <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                    <div>
                                        <div class="font-weight-semibold">{{ translate(ucfirst(str_replace('_', ' ', $charge->charge_type))) }}</div>
                                        @if ($charge->description)
                                            <div class="text-muted small">{{ $charge->description }}</div>
                                        @endif
                                    </div>
                                    <span class="badge badge-soft-danger">{{ \App\CentralLogics\Helpers::format_currency($charge->amount) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="btn--container justify-content-start flex-wrap">
                            @if ($booking->status === 'pending')
                                <form action="{{ route('vendor.equipment-bookings.confirm', $booking->id) }}" method="post">
                                    @csrf
                                    <button type="submit" class="btn btn--primary">
                                        <i class="tio-checkmark-circle"></i> {{ translate('Confirm Booking') }}
                                    </button>
                                </form>
                                <form action="{{ route('vendor.equipment-bookings.cancel', $booking->id) }}" method="post">
                                    @csrf
                                    <button type="submit" class="btn btn--danger">
                                        <i class="tio-delete-outlined"></i> {{ translate('Cancel Booking') }}
                                    </button>
                                </form>
                            @elseif ($booking->status === 'confirmed')
                                <form action="{{ route('vendor.equipment-bookings.mark-active', $booking->id) }}" method="post">
                                    @csrf
                                    <button type="submit" class="btn btn--primary">
                                        <i class="tio-cart"></i> {{ translate('Mark as Picked Up (Active)') }}
                                    </button>
                                </form>
                                <form action="{{ route('vendor.equipment-bookings.cancel', $booking->id) }}" method="post">
                                    @csrf
                                    <button type="submit" class="btn btn--danger">
                                        <i class="tio-delete-outlined"></i> {{ translate('Cancel Booking') }}
                                    </button>
                                </form>
                            @elseif (in_array($booking->status, ['active', 'overdue']))
                                <form action="{{ route('vendor.equipment-bookings.mark-returned', $booking->id) }}" method="post" class="d-flex align-items-end flex-wrap gap-2">
                                    @csrf
                                    <div>
                                        <label class="form-label m-0">{{ translate('Late Fee') }}</label>
                                        <input type="number" name="late_fee" min="0" step="0.01" value="0" class="form-control">
                                    </div>
                                    <div>
                                        <label class="form-label m-0">{{ translate('Description') }}</label>
                                        <input type="text" name="late_fee_description" maxlength="255" class="form-control" placeholder="{{ translate('Optional') }}">
                                    </div>
                                    <button type="submit" class="btn btn--primary">
                                        <i class="tio-done"></i> {{ translate('Mark as Returned (Completed)') }}
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
