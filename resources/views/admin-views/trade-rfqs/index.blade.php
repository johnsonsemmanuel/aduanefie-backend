@extends('layouts.admin.app')

@section('title', 'Cross-Border Trade RFQs')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-document-text-outlined"></i></span>
                <span>Cross-Border Agro RFQs &amp; Supplier Quotes</span>
            </h1>
            <p class="text-muted mb-0">
                Review bulk buyer sourcing requests, compare farm/cooperative quotes, and award cross-border contracts.
            </p>
        </div>
        <a href="https://aduanefie.com/trade" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm">
            <i class="tio-open-in-new"></i> View Live Trade Hub
        </a>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Active Sourcing Requests ({{ $rfqs->count() }})</h5>
            <form action="{{ route('admin.trade-rfqs.index') }}" method="GET" class="d-flex gap-2">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="open" {{ request('status') === 'open' ? 'selected' : '' }}>Open</option>
                    <option value="quoted" {{ request('status') === 'quoted' ? 'selected' : '' }}>Quoted</option>
                    <option value="awarded" {{ request('status') === 'awarded' ? 'selected' : '' }}>Awarded</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>RFQ #</th>
                        <th>Buyer</th>
                        <th>Commodity &amp; Volume</th>
                        <th>Corridor &amp; Incoterm</th>
                        <th>Compliance</th>
                        <th>Quotes</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rfqs as $rfq)
                        @php $quotes = $quotesByRfq->get($rfq->id, collect()); @endphp
                        <tr>
                            <td><strong>#RFQ-{{ $rfq->id }}</strong></td>
                            <td>
                                <strong>{{ $rfq->buyer_name }}</strong>
                                @if ($rfq->buyer_company)
                                    <div class="text-muted small">{{ $rfq->buyer_company }}</div>
                                @endif
                                <div class="text-muted small">{{ $rfq->buyer_phone }}</div>
                            </td>
                            <td>
                                <strong>{{ $rfq->commodity }}</strong>
                                <div class="text-muted small">
                                    {{ number_format($rfq->quantity, 0) }} {{ $rfq->unit }}
                                    @if ($rfq->target_price_ghs)
                                        &bull; Target: GH₵{{ number_format($rfq->target_price_ghs, 2) }}
                                    @endif
                                </div>
                            </td>
                            <td>
                                <span>{{ $rfq->origin_country }} &rarr; {{ $rfq->destination_country }}</span>
                                <div class="text-muted small">{{ $rfq->trade_bloc }} &bull; {{ $rfq->incoterm }}</div>
                            </td>
                            <td class="small">
                                @if ($rfq->requires_insurance)
                                    <span class="badge badge-soft-success">Insured</span>
                                @endif
                                @if ($rfq->requires_phytosanitary)
                                    <span class="badge badge-soft-info">Phytosanitary</span>
                                @endif
                            </td>
                            <td>
                                @if ($quotes->isEmpty())
                                    <span class="text-muted small">No quotes yet</span>
                                @else
                                    @foreach ($quotes as $q)
                                        <div class="border rounded p-1 mb-1 small d-flex justify-content-between align-items-center gap-2">
                                            <div>
                                                <strong>{{ $q->supplier_name }}</strong>: GH₵{{ number_format($q->quoted_unit_price_ghs, 2) }}
                                                <div class="text-muted">{{ $q->lead_time_days }}d &bull; {{ $q->compliance_docs }}</div>
                                            </div>
                                            @if ($q->status === 'accepted')
                                                <span class="badge badge-success">Accepted</span>
                                            @else
                                                <form action="{{ route('admin.trade-rfqs.accept-quote', $q->id) }}" method="POST">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-outline-success">Accept</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                @endif
                            </td>
                            <td>
                                <form action="{{ route('admin.trade-rfqs.update-status', $rfq->id) }}" method="POST" class="d-flex gap-1">
                                    @csrf
                                    <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                                        <option value="open" {{ $rfq->status === 'open' ? 'selected' : '' }}>Open</option>
                                        <option value="quoted" {{ $rfq->status === 'quoted' ? 'selected' : '' }}>Quoted</option>
                                        <option value="awarded" {{ $rfq->status === 'awarded' ? 'selected' : '' }}>Awarded</option>
                                        <option value="closed" {{ $rfq->status === 'closed' ? 'selected' : '' }}>Closed</option>
                                    </select>
                                </form>
                            </td>
                            <td class="text-right">
                                <form action="{{ route('admin.trade-rfqs.destroy', $rfq->id) }}" method="POST" onsubmit="return confirm('Delete this RFQ?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="tio-delete-outlined"></i></button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No cross-border RFQs submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
