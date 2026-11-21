@extends('marketer.layout')

@section('title', 'Backyard Farm Verification')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}<br>
    <span class="small">{{ $marketer->email }}</span>
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'backyard'])
@endsection

@section('content')
    <div class="card">
        <h1>Backyard Farm Field Verification</h1>
        <p class="muted small" style="margin-top:0;">
            Verify backyard farms in your region after a site or video visit. Verified applications move straight to Admin for store activation and attribute to your marketer account.
        </p>

        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
            @foreach(['pending' => 'Pending', 'verified' => 'Verified', 'approved' => 'Approved', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                <a class="btn {{ $status === $key ? '' : 'secondary' }}" style="padding:6px 12px;font-size:13px;"
                   href="{{ route('marketer.backyard-farms', ['status' => $key]) }}">{{ $label }}</a>
            @endforeach
        </div>

        @if(count($applications) === 0)
            <p class="muted">No backyard farm applications found for this filter.</p>
        @else
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Farm</th>
                            <th>Applicant</th>
                            <th>Crops &amp; Size</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Field Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($applications as $app)
                            <tr>
                                <td><strong>{{ $app->farm_name }}</strong><br><span class="small muted">{{ $app->zone?->name ?? '-' }}</span></td>
                                <td>{{ $app->guest_phone ?? '-' }}<br><span class="small muted">{{ $app->guest_email ?? '-' }}</span></td>
                                <td>{{ $app->primary_crops }}<br><span class="small muted">{{ $app->farm_size_acres }} acres</span></td>
                                <td class="small">{{ $app->address }}</td>
                                <td><span class="badge">{{ ucfirst($app->status) }}</span></td>
                                <td>
                                    @if($app->status === 'pending')
                                        <form method="post" action="{{ route('marketer.backyard-farms.verify', $app->id) }}" style="display:flex;flex-direction:column;gap:6px;min-width:190px;">
                                            @csrf
                                            <input type="text" name="verification_notes" placeholder="Site visit notes..." style="padding:6px;font-size:12px;">
                                            <div style="display:flex;gap:6px;">
                                                <button type="submit" name="action" value="verify" style="padding:6px 10px;font-size:12px;">Verify Farm</button>
                                                <button type="submit" name="action" value="reject" class="btn secondary" style="padding:6px 10px;font-size:12px;">Reject</button>
                                            </div>
                                        </form>
                                    @else
                                        <span class="small muted">{{ $app->verification_notes ?? $app->rejection_reason ?? '-' }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($applications, 'links'))
                <div style="margin-top:16px;">{{ $applications->links() }}</div>
            @endif
        @endif
    </div>
@endsection
