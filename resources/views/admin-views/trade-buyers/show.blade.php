@extends('layouts.admin.app')

@section('title', 'Review Trade Buyer #' . $profile->id)

@section('content')
<div class="content container-fluid">
    <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h1 class="page-header-title">
                <span class="page-header-icon"><i class="tio-user-big-outlined"></i></span>
                <span>Review Trade Buyer Application #{{ $profile->id }}</span>
            </h1>
            <p class="text-muted mb-0">Applicant: {{ $profile->applicant_name }} &bull; Submitted: {{ $profile->created_at->format('d M Y, H:i') }}</p>
        </div>
        <a href="{{ route('admin.trade-buyers.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="tio-arrow-backward"></i> Back to Applicants
        </a>
    </div>

    <div class="row">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Buyer &amp; Sourcing Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4 text-muted">Applicant Name</dt>
                        <dd class="col-sm-8 font-weight-bold">{{ $profile->applicant_name }}</dd>

                        <dt class="col-sm-4 text-muted">Organisation</dt>
                        <dd class="col-sm-8">
                            {{ $profile->organization_name ?: 'Individual Trader' }}
                            @if(!$profile->organization_name)
                                <span class="badge badge-soft-info ml-1">Individual</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4 text-muted">Country</dt>
                        <dd class="col-sm-8">{{ $profile->country_of_operation }}</dd>

                        <dt class="col-sm-4 text-muted">Contact Phone</dt>
                        <dd class="col-sm-8">{{ $profile->contact_phone }}</dd>

                        <dt class="col-sm-4 text-muted">Contact Email</dt>
                        <dd class="col-sm-8">{{ $profile->contact_email ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Business Type</dt>
                        <dd class="col-sm-8">{{ $profile->business_type ? ucfirst(str_replace('_', ' ', $profile->business_type)) : '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Registration / Tax ID</dt>
                        <dd class="col-sm-8">{{ $profile->tax_or_reg_number ?: 'Not provided' }}</dd>

                        <dt class="col-sm-4 text-muted">Target Commodities</dt>
                        <dd class="col-sm-8">
                            @if(is_array($profile->target_commodities) && count($profile->target_commodities))
                                @foreach($profile->target_commodities as $item)
                                    <span class="badge badge-soft-secondary mr-1">{{ $item }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4 text-muted">Intended Destinations</dt>
                        <dd class="col-sm-8">
                            @if(is_array($profile->intended_destinations) && count($profile->intended_destinations))
                                @foreach($profile->intended_destinations as $dest)
                                    <span class="badge badge-soft-primary mr-1">{{ $dest }}</span>
                                @endforeach
                            @else
                                <span class="text-muted">Not specified</span>
                            @endif
                        </dd>

                        <dt class="col-sm-4 text-muted">Estimated Volume</dt>
                        <dd class="col-sm-8">{{ $profile->estimated_volume ?: '—' }}</dd>

                        <dt class="col-sm-4 text-muted">Sourcing Notes</dt>
                        <dd class="col-sm-8">{{ $profile->sourcing_notes ?: '—' }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Audit Trail -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Immutable Review Audit Trail ({{ $profile->auditLogs->count() }})</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-align-middle card-table">
                        <thead>
                            <tr>
                                <th>Date &amp; Time</th>
                                <th>Actor</th>
                                <th>Transition</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($profile->auditLogs as $log)
                            <tr>
                                <td class="small text-muted">{{ $log->created_at->format('d M Y, H:i:s') }}</td>
                                <td>
                                    <span class="badge badge-soft-dark">{{ ucfirst($log->actor_type) }}</span>
                                    @if($log->actor_id)
                                        <span class="small text-muted">#{{ $log->actor_id }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="small">{{ $log->from_status ?: 'none' }} &rarr; <strong>{{ $log->to_status }}</strong></span>
                                </td>
                                <td class="small">{{ $log->notes ?: '—' }}</td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="text-center py-2 text-muted small">No audit log entries yet.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <!-- Current Status & Action Box -->
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="card-title mb-0">Status &amp; Decision</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small mb-1">Current State</label>
                        <div>
                            @if($profile->status === 'approved')
                                <span class="badge badge-success p-2 font-size-base">Approved</span>
                                <div class="small text-muted mt-1">Approved at: {{ $profile->approved_at ? $profile->approved_at->format('d M Y, H:i') : '—' }}</div>
                            @elseif($profile->status === 'under_review')
                                <span class="badge badge-warning p-2 font-size-base">Under Review</span>
                            @elseif($profile->status === 'pending')
                                <span class="badge badge-info p-2 font-size-base">Pending Initial Review</span>
                            @elseif($profile->status === 'rejected')
                                <span class="badge badge-danger p-2 font-size-base">Rejected</span>
                                @if($profile->rejection_reason)
                                    <div class="small text-danger mt-1">Reason: {{ $profile->rejection_reason }}</div>
                                @endif
                            @elseif($profile->status === 'revoked')
                                <span class="badge badge-dark p-2 font-size-base">Revoked</span>
                                @if($profile->revocation_reason)
                                    <div class="small text-danger mt-1">Reason: {{ $profile->revocation_reason }}</div>
                                @endif
                            @endif
                        </div>
                    </div>

                    <hr>

                    <!-- Transition Form -->
                    <h6 class="mb-2">Execute Status Transition</h6>
                    <form action="{{ route('admin.trade-buyers.transition', $profile->id) }}" method="POST">
                        @csrf
                        <div class="form-group mb-3">
                            <label class="small text-muted">Target Status</label>
                            <select name="status" class="form-control" required>
                                <option value="">-- Select Valid Transition --</option>
                                @if(in_array($profile->status, ['pending'], true))
                                    <option value="under_review">Mark Under Review</option>
                                    <option value="approved">Approve Access</option>
                                    <option value="rejected">Reject Application</option>
                                @elseif(in_array($profile->status, ['under_review'], true))
                                    <option value="approved">Approve Access</option>
                                    <option value="rejected">Reject Application</option>
                                    <option value="pending">Return to Pending</option>
                                @elseif(in_array($profile->status, ['approved'], true))
                                    <option value="revoked">Revoke Access</option>
                                @elseif(in_array($profile->status, ['revoked'], true))
                                    <option value="under_review">Reinstate to Under Review</option>
                                    <option value="approved">Reinstate to Approved</option>
                                @elseif(in_array($profile->status, ['rejected'], true))
                                    <option value="" disabled>Awaiting applicant resubmission</option>
                                @endif
                            </select>
                        </div>

                        <div class="form-group mb-3">
                            <label class="small text-muted">Decision Notes / Reason</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Enter reason or operational notes for the audit trail..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary btn-block" onclick="return confirm('Confirm transition of buyer status? This action is recorded in the audit trail.')">
                            Submit Transition
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
