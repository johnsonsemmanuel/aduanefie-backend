@extends('layouts.admin.app')

@section('title', 'Cross-Border Trade Buyers')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-user-big-outlined"></i></span>
                <span>Cross-Border Trade Buyer Applications</span>
            </h1>
            <p class="text-muted mb-0">
                Authorised review of commercial and individual agricultural trade sourcing applications.
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Applicant Profiles ({{ $profiles->total() }})</h5>
            <form action="{{ route('admin.trade-buyers.index') }}" method="GET" class="d-flex gap-2">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                    <option value="all" {{ request('status') === 'all' || !request('status') ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ request('status') === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="under_review" {{ request('status') === 'under_review' ? 'selected' : '' }}>Under Review</option>
                    <option value="approved" {{ request('status') === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>Rejected</option>
                    <option value="revoked" {{ request('status') === 'revoked' ? 'selected' : '' }}>Revoked</option>
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>ID</th>
                        <th>Applicant</th>
                        <th>Organisation / Trader</th>
                        <th>Country</th>
                        <th>Contact</th>
                        <th>Status</th>
                        <th>Applied At</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($profiles as $profile)
                    <tr>
                        <td>#{{ $profile->id }}</td>
                        <td>
                            <strong>{{ $profile->applicant_name }}</strong>
                            @if($profile->user)
                                <div class="text-muted small">User ID: {{ $profile->user_id }}</div>
                            @endif
                        </td>
                        <td>
                            @if($profile->organization_name)
                                <span>{{ $profile->organization_name }}</span>
                            @else
                                <span class="badge badge-soft-info">Individual Trader</span>
                            @endif
                            @if($profile->business_type)
                                <div class="text-muted small">{{ ucfirst(str_replace('_', ' ', $profile->business_type)) }}</div>
                            @endif
                        </td>
                        <td>{{ $profile->country_of_operation }}</td>
                        <td>
                            <div>{{ $profile->contact_phone }}</div>
                            @if($profile->contact_email)
                                <div class="text-muted small">{{ $profile->contact_email }}</div>
                            @endif
                        </td>
                        <td>
                            @if($profile->status === 'approved')
                                <span class="badge badge-success">Approved</span>
                            @elseif($profile->status === 'under_review')
                                <span class="badge badge-warning">Under Review</span>
                            @elseif($profile->status === 'pending')
                                <span class="badge badge-info">Pending</span>
                            @elseif($profile->status === 'rejected')
                                <span class="badge badge-danger">Rejected</span>
                            @elseif($profile->status === 'revoked')
                                <span class="badge badge-dark">Revoked</span>
                            @endif
                        </td>
                        <td>{{ $profile->created_at ? $profile->created_at->format('d M Y, H:i') : '—' }}</td>
                        <td>
                            <a href="{{ route('admin.trade-buyers.show', $profile->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="tio-visible"></i> Review
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No trade buyer applications found.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">
            {{ $profiles->links() }}
        </div>
    </div>
</div>
@endsection
