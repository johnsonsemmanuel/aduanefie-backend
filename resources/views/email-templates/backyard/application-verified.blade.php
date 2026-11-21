@extends('email-templates.backyard.layout')

@section('content')
<h2>Application Verified ✅</h2>
<p>Hello <strong>{{ $farmName }}</strong> farmer,</p>

<p>Great news! Your backyard farm has been <strong>verified</strong> by a local marketer. Your farm is now one step closer to going live on Aduanefie.</p>

<div class="info-box">
    <p><strong>Farm:</strong> {{ $farmName }}</p>
    <p><strong>Status:</strong> <span class="badge">Verified</span></p>
    <p><strong>Verified by:</strong> {{ $verifierName }}</p>
    <p><strong>Verified at:</strong> {{ $verifiedAt }}</p>
    @if ($notes)
        <p><strong>Marketer notes:</strong> {{ $notes }}</p>
    @endif
</div>

<p>Your application is now pending final admin approval. Once approved, your vendor store will be created and you can start adding backyard products.</p>

<p><a href="{{ $statusUrl }}" class="btn">Check Application Status</a></p>

<p>The Aduanefie Team</p>
@endsection
