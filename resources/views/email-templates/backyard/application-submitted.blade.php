@extends('email-templates.backyard.layout')

@section('content')
<h2>Application Submitted</h2>
<p>Hello <strong>{{ $farmName }}</strong> farmer,</p>

<p>Thank you for applying to grow produce in your backyard on Aduanefie! We've received your application and it is now under review.</p>

<div class="info-box">
    <p><strong>Farm:</strong> {{ $farmName }}</p>
    <p><strong>Size:</strong> {{ $sizeAcres }} acres</p>
    <p><strong>Primary Crops:</strong> {{ $primaryCrops }}</p>
    <p><strong>Status:</strong> <span class="badge">Pending Review</span></p>
    <p><strong>Submitted:</strong> {{ $submittedAt }}</p>
</div>

<p>A local marketer will contact you shortly to schedule a farm visit and verify your backyard growing space. Once verified and approved, your farm will become a vendor store on Aduanefie and your products will carry a "Backyard Grown" badge.</p>

<p>You can check your application status anytime at: <a href="{{ $statusUrl }}">{{ $statusUrl }}</a></p>

<p>Happy growing!<br>The Aduanefie Team</p>
@endsection
