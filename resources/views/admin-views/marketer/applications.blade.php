@extends('layouts.admin.app')

@section('title', 'Remote Marketer Applications')

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title mb-1">Remote Marketer Applications</h1>
            <p class="text-muted mb-0">Review regional marketer applications. Approving automatically creates a Role-5 marketer account, assigns a referral code, and SMSs credentials.</p>
        </div>
        <div class="btn-group">
            @foreach(['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $k => $lbl)
                <a href="{{ route('admin.marketer-applications.index', ['status' => $k]) }}"
                   class="btn btn-sm {{ $status === $k ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $lbl }}</a>
            @endforeach
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-borderless table-thead-bordered table-align-middle">
                <thead class="thead-light">
                    <tr>
                        <th>Applicant</th>
                        <th>Region &amp; District</th>
                        <th>MoMo Network</th>
                        <th>Experience</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($applications as $app)
                        <tr>
                            <td>
                                <strong>{{ $app->full_name }}</strong><br>
                                <small class="text-muted">{{ $app->email }} · {{ $app->phone }}</small>
                            </td>
                            <td>{{ $app->region }}@if($app->district)<br><small class="text-muted">{{ $app->district }}</small>@endif</td>
                            <td>{{ $app->momo_network ?? '-' }}</td>
                            <td style="max-width:260px;"><small>{{ $app->experience_notes ?? '-' }}</small></td>
                            <td><span class="badge badge-soft-{{ $app->status === 'approved' ? 'success' : ($app->status === 'rejected' ? 'danger' : 'warning') }}">{{ ucfirst($app->status) }}</span></td>
                            <td>
                                @if($app->status === 'pending')
                                    <form method="post" action="{{ route('admin.marketer-applications.approve', $app->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-success">Approve &amp; Create</button>
                                    </form>
                                    <form method="post" action="{{ route('admin.marketer-applications.reject', $app->id) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                @else
                                    <small class="text-muted">{{ optional($app->reviewed_at)->format('M d, Y') }}</small>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-4">No marketer applications for this filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer">{{ $applications->links() }}</div>
    </div>
</div>
@endsection
