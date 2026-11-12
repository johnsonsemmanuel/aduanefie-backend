@extends('layouts.master')

@section('content')
<div class="container">
    <h1>Service Bookings</h1>
    @foreach($bookings as $booking)
        <p>Booking #{{ $booking->id }} - {{ $booking->status }} - ${{ $booking->total_amount }}</p>
    @endforeach
</div>
@endsection
