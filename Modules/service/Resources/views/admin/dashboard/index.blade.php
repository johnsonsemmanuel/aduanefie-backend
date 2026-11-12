@extends('layouts.master')

@section('content')
<div class="container">
    <h1>Agro Services Dashboard</h1>
    <p>Service module is active. Categories: {{ $stats['total_categories'] }}, Services: {{ $stats['total_services'] }}, Bookings: {{ $stats['total_bookings'] }}</p>
</div>
@endsection
