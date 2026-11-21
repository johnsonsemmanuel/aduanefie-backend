@extends('layouts.admin.app')

@section('title', 'Trade & Crop Insurance Claims')

@section('content')
    <div class="content container-fluid">
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-sm mb-2 mb-sm-0">
                    <h1 class="page-header-title">
                        <span class="page-header-icon">
                            <i class="tio-shield-outlined"></i>
                        </span>
                        <span>Trade & Crop Insurance Claims</span>
                        <span class="badge badge-soft-dark ml-2">{{ $counts['all'] }}</span>
                    </h1>
                </div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach (['all' => 'All', 'pending' => 'Pending', 'approved' => 'Approved', 'paid' => 'Paid', 'rejected' => 'Rejected'] as $key => $label)
                <a href="{{ route('admin.insurance-claims.index', ['status' => $key]) }}"
                   class="btn btn-sm {{ $status === $key ? 'btn--primary' : 'btn-outline-secondary' }}">
                    {{ $label }} ({{ $counts[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        <div class="card">
            <div class="table-responsive datatable-custom">
                <table class="table table-hover table-borderless table-thead-bordered table-nowrap table-align-middle card-table">
                    <thead class="thead-light">
                        <tr>
                            <th>Claim #</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Reason & Details</th>
                            <th>Claim Amount</th>
                            <th>Status</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    @forelse ($claims as $claim)
                        <tr>
                            <td><strong>#{{ $claim->id }}</strong><br><small class="text-muted">{{ $claim->created_at?->format('d M Y H:i') }}</small></td>
                            <td>
                                <a href="{{ route('admin.order.details', ['id' => $claim->order_id]) }}">#{{ $claim->order_id }}</a>
                            </td>
                            <td>
                                {{ trim(($claim->user->f_name ?? '') . ' ' . ($claim->user->l_name ?? '')) ?: 'Customer #' . $claim->user_id }}<br>
                                <small class="text-muted">{{ $claim->user->phone ?? $claim->user->email ?? '' }}</small>
                            </td>
                            <td style="max-width:300px;white-space:normal;">
                                <strong>{{ $claim->reason }}</strong><br>
                                <small class="text-muted">{{ $claim->description }}</small>
                            </td>
                            <td>{{ \App\CentralLogics\Helpers::format_currency($claim->claim_amount) }}</td>
                            <td>
                                @php($badge = match($claim->status) {
                                    'approved', 'paid' => 'badge-soft-success',
                                    'rejected' => 'badge-soft-danger',
                                    default => 'badge-soft-warning',
                                })
                                <span class="badge {{ $badge }} text-capitalize">{{ $claim->status }}</span>
                            </td>
                            <td>
                                <form method="post" action="{{ route('admin.insurance-claims.status', $claim->id) }}" class="d-flex flex-column gap-1" style="min-width:210px;">
                                    @csrf
                                    <select name="status" class="form-control form-control-sm">
                                        @foreach (['pending', 'approved', 'paid', 'rejected'] as $st)
                                            <option value="{{ $st }}" {{ $claim->status === $st ? 'selected' : '' }}>{{ ucfirst($st) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="admin_note" value="{{ $claim->admin_note }}" placeholder="Admin note..." class="form-control form-control-sm">
                                    <button type="submit" class="btn btn-sm btn--primary">Update</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No insurance claims found.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($claims, 'links'))
                <div class="card-footer">
                    {!! $claims->links() !!}
                </div>
            @endif
        </div>
    </div>
@endsection
