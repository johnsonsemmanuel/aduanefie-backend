@extends('layouts.master')

@section('content')
<div class="container">
    <h1>Provider Dashboard</h1>
    <p>Provider stats: {{ $stats['total_services'] }} services, {{ $stats['total_bookings'] }} bookings.</p>
</div>
@endsection
