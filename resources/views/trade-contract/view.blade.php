@extends('layouts.landing.app')
@section('title', 'Trade Contract #{{ $contract->id }} | ' . __('messages.landing_page'))
@section('content')

<!-- ==== Banner Section Starts Here ==== -->
<section class="banner-section position-relative">
    <div class="container">
        <div class="banner-content wow fadeInUp">
            <h1 class="title">Trade Contract #{{ $contract->id }}</h1>
            <div class="text">
                <strong>Status:</strong> {{ $contract->getStatusLabel() }} <br>
                <strong>Amount:</strong> {{ \App\CentralLogics\Helpers::format_currency($contract->contract_amount) }} {{ $contract->currency_code }} <br>
                <strong>Escrow:</strong> {{ \App\CentralLogics\Helpers::format_currency($contract->escrow_amount) }} {{ $contract->currency_code }}
            </div>
        </div>
        <div class="px-xl-5 d-flex justify-content-center text-base banner-svg-img">
            <img src="{{asset('public/assets/landing/img/main-banner.svg')}}" class="svg" alt="">
        </div>
    </section>
    <!-- ==== Banner Section Ends Here ==== -->

    <!-- ==== Contract Details Starts Here ==== -->
    <section class="ecommerce-venture-section pt-80 pb-80">
        <div class="container max-w1060">

            <!-- Contract Status Badge -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge {{ $contract->getStatusLabel() == 'Funds in Escrow' ? 'bg--success text-white' : ($contract->getStatusLabel() == 'Awaiting Deposit' ? 'bg--warning text-dark' : 'bg--primary text-white') }} fs-6">
                            {{ $contract->getStatusLabel() }}
                        </span>
                        @if ($contract->assurance)
                        <span class="badge {{ $contract->assurance->getBadgeClass() }} fs-6">
                            {{ $contract->assurance->getBadgeLabel() }}
                        </span>
                        @endif
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <!-- Trade Details -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg--primary text-white">
                            <h5 class="mb-0">{{ translate('messages.trade_details') }}</h5>
                        </div>
                        <div class="card-body p-4">
                            <table class="table table-borderless">
                                <tbody>
                                <tr>
                                    <td class="text-muted small" style="width: 30%;">{{ translate('messages.product') }}</td>
                                    <td class="fw-bold">{{ ucfirst($contract->tradeRequest->product->name ?? 'Commodity') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.quantity') }}</td>
                                    <td>{{ $contract->tradeRequest->quantity }} {{ $contract->tradeRequest->unit }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.origin') }}</td>
                                    <td>{{ ucfirst($contract->tradeRequest->origin_country ?? 'Unknown') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.destination') }}</td>
                                    <td>{{ ucfirst($contract->tradeRequest->destination_country) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.incoterm') }}</td>
                                    <td>{{ $contract->tradeRequest->incoterm }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.contract_amount') }}</td>
                                    <td class="fw-bold">{{ \App\CentralLogics\Helpers::format_currency($contract->contract_amount) }} {{ $contract->currency_code }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.escrow_amount') }}</td>
                                    <td class="fw-bold">{{ \App\CentralLogics\Helpers::format_currency($contract->escrow_amount) }} {{ $contract->currency_code }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted small">{{ translate('messages.created_at') }}</td>
                                    <td>{{ $contract->created_at->format('Y-m-d H:i') }}</td>
                                </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-header bg--primary text-white">
                            <h5 class="mb-0">{{ translate('messages.actions') }}</h5>
                        </div>
                        <div class="card-body p-4">
                            <div class="row g-3">

                                @if ($canDeposit)
                                <div class="col-md-6">
                                    <form action="{{ route('trade-contracts.deposit', $contract->id) }}" method="POST" id="deposit_form_{{ $contract->id }}">
                                        @csrf
                                        <input type="hidden" name="amount" value="{{ $contract->contract_amount }}">
                                        <button type="button" class="btn btn--primary w-100" onclick="openDepositModal({{ $contract->id }}, '{{ $contract->contract_amount }}')">
                                            {{ translate('messages.deposit_into_escrow') }}
                                        </button>
                                    </form>
                                </div>
                                @endif

                                @if ($canConfirmDelivery)
                                <div class="col-md-6">
                                    <form action="{{ route('trade-contracts.confirm-delivery', $contract->id) }}" method="POST" onsubmit="return confirm('{{ translate('messages.confirm_delivery_prompt') }}')">
                                        @csrf
                                        <input type="hidden" name="delivery_confirmed" value="1">
                                        <button type="submit" class="btn btn--info w-100">
                                            {{ translate('messages.confirm_delivery') }}
                                        </button>
                                    </form>
                                </div>
                                @endif

                                @if ($canVerifyDocs)
                                <div class="col-md-6">
                                    <form action="{{ route('trade-contracts.verify-documents', $contract->id) }}" method="POST" onsubmit="return confirm('{{ translate('messages.verify_docs_prompt') }}')">
                                        @csrf
                                        <input type="hidden" name="docs_verified" value="1">
                                        <button type="submit" class="btn btn--warning w-100">
                                            {{ translate('messages.verify_documents') }}
                                        </button>
                                    </form>
                                </div>
                                @endif

                                @if ($canRelease)
                                <div class="col-md-6">
                                    <form action="{{ route('trade-contracts.release', $contract->id) }}" method="POST" onsubmit="return confirm('{{ translate('messages.release_funds_prompt') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn--success w-100">
                                            {{ translate('messages.release_funds') }}
                                        </button>
                                    </form>
                                </div>
                                @endif

                                @if ($canRefund && !$canRelease)
                                <div class="col-md-6">
                                    <form action="{{ route('trade-contracts.refund', $contract->id) }}" method="POST" onsubmit="return confirm('{{ translate('messages.refund_prompt') }}')">
                                        @csrf
                                        <button type="submit" class="btn btn--danger w-100">
                                            {{ translate('messages.refund_funds') }}
                                        </button>
                                    </form>
                                </div>
                                @endif

                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trade Assurance -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg--primary text-white">
                            <h5 class="mb-0">{{ translate('messages.trade_assurance') }}</h5>
                        </div>
                        <div class="card-body p-4">
                            @if ($contract->assurance)
                            <div class="text-center mb-4">
                                <span class="badge {{ $contract->assurance->getBadgeClass() }} fs-5 mb-2">
                                    {{ $contract->assurance->getBadgeLabel() }}
                                </span>
                                <p class="text-muted small mb-3">{{ $contract->assurance->inspection_type ?? 'Quality & Quantity' }} Inspection</p>
                                @if ($contract->assurance->inspection_date)
                                <small class="text-muted d-block">{{ translate('messages.inspected_on') }}: {{ $contract->assurance->inspection_date->format('Y-m-d') }}</small>
                                @endif
                                @if ($contract->assurance->inspector_name)
                                <small class="text-muted d-block">{{ translate('messages.inspector') }}: {{ $contract->assurance->inspector_name }}</small>
                                @endif
                                @if ($contract->assurance->certificate_number)
                                <small class="text-muted d-block">{{ translate('messages.certificate_number') }}: {{ $contract->assurance->certificate_number }}</small>
                                @endif
                            </div>
                            @else
                            <div class="text-center py-4">
                                <i class="tio-shield tio-3x text-muted mb-3"></i>
                                <p class="text-muted small">{{ translate('messages.no_assurance_yet') }}</p>
                                <a href="{{ route('trade-assurance.request', $contract->id) }}" class="btn btn--outline-primary btn-sm">
                                    {{ translate('messages.request_assurance') }}
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ==== Contract Details Ends Here ==== -->

@endsection

@push('css_or_js')
<script>
    function openDepositModal(contractId, amount) {
        const contractName = 'Trade Contract #' + contractId;
        window.openEscrowDeposit(contractId, contractName);
        document.getElementById('escrow_deposit_amount').value = amount;
    }
</script>
@endpush
