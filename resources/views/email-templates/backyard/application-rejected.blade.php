@extends('email-templates.backyard.layout')

@section('content')
<h2 style="color:#d32f2f;">Application Not Approved</h2>
<p>Hello <strong>{{ $farmName }}</strong> farmer,</p>

<p>Thank you for your interest in growing produce in your backyard on Aduanefie. After review, we are unable to approve your application at this time.</p>

<div class="info-box" style="background-color:#ffebee;border-left-color:#d32f2f;">
    <p><strong>Farm:</strong> {{ $farmName }}</p>
    <p><strong>Status:</strong> <span class="badge" style="background-color:#d32f2f;color:#fff;">Rejected</span></p>
    @if ($reason)
        <p><strong>Reason:</strong> {{ $reason }}</p>
    @endif
</div>

<p>You're welcome to apply again with updated information. Common reasons for rejection include incomplete farm details, missing photos, or location outside our service area.</p>

<p><a href="{{ $applyUrl }}" class="btn">Apply Again</a></p>

<p>The Aduanefie Team</p>
@endsection
