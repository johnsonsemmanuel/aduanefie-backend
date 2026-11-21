@extends('layouts.admin.app')

@section('title', 'Organiser Verification')

@section('content')
<div class="content container-fluid">
    <div class="page-header">
        <h1 class="page-header-title">
            <span class="page-header-icon"><i class="tio-verified"></i></span>
            <span>Organiser Verification</span>
        </h1>
        <p class="text-muted mb-0">
            Review Ghana Card references for group organisers. Only the last four characters are stored.
        </p>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0">Submissions</h5>
            <form action="{{ route('admin.organizer-verifications.index') }}" method="GET" class="d-flex gap-2">
                <select name="status" class="form-control form-control-sm" onchange="this.form.submit()">
                    @foreach(['pending', 'verified', 'rejected', 'all'] as $option)
                        <option value="{{ $option }}" {{ $status === $option ? 'selected' : '' }}>
                            {{ ucfirst($option) }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table table-hover table-borderless table-thead-bordered table-align-middle card-table">
                <thead class="thead-light">
                    <tr>
                        <th>Applicant</th>
                        <th>Card reference</th>
                        <th>Completed external orders</th>
                        <th>Submitted</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $row)
                        @php($user = $row['user'])
                        @php($trust = $row['trust'])
                        <tr>
                            <td>
                                <div class="font-weight-bold">{{ $user->full_name }}</div>
                                <div class="text-muted small">{{ $user->phone }}</div>
                            </td>
                            <td>****{{ $user->ghana_card_last4 }}</td>
                            <td>
                                {{ $trust['completed_external_orders'] }} / {{ $trust['required_orders'] }}
                                {!! $trust['orders_met'] ? '<span class="badge badge-soft-success">Met</span>' : '<span class="badge badge-soft-warning">Short</span>' !!}
                            </td>
                            <td>{{ $user->organizer_verification_submitted_at?->format('Y-m-d H:i') ?? '-' }}</td>
                            <td><span class="badge badge-soft-primary">{{ $trust['status'] }}</span></td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <form action="{{ route('admin.organizer-verifications.approve', $user->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-success">Approve</button>
                                    </form>
                                    <form action="{{ route('admin.organizer-verifications.reject', $user->id) }}" method="POST" class="d-flex gap-1">
                                        @csrf
                                        <input type="text" name="note" maxlength="500" class="form-control form-control-sm" placeholder="Reason (optional)">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">No submissions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="card-footer">{{ $users->links() }}</div>
    </div>
</div>
@endsection
