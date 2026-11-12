@extends('layouts.master')

@section('content')
<div class="container">
    <h1>My Bookings</h1>
    @foreach($bookings as $booking)
        <p>Booking #{{ $booking->id }} - {{ $booking->status }}</p>
    @endforeach
</div>
@endsection
