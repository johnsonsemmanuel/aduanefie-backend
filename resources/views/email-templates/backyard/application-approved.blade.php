@extends('email-templates.backyard.layout')

@section('content')
<h2>Application Approved 🎉</h2>
<p>Hello <strong>{{ $farmName }}</strong> farmer,</p>

<p>Congratulations! Your backyard farm has been <strong>approved</strong> and is now live on Aduanefie!</p>

<div class="info-box">
    <p><strong>Farm:</strong> {{ $farmName }}</p>
    <p><strong>Status:</strong> <span class="badge" style="background-color:#4caf50;color:#fff;">Approved</span></p>
    <p><strong>Store:</strong> {{ $storeName }}</p>
    <p><strong>Approved at:</strong> {{ $approvedAt }}</p>
</div>

<p>Your vendor store has been created. You can now log in to your vendor dashboard to add backyard products, set prices, and manage orders. Your products will appear in nearby farming discovery with a "Backyard Grown" badge.</p>

<p><a href="{{ $vendorLoginUrl }}" class="btn">Go to Vendor Dashboard</a></p>

<p>Thank you for growing with Aduanefie!<br>The Aduanefie Team</p>
@endsection
