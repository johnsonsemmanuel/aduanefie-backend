@extends('layouts.master')

@section('content')
<div class="container">
    <h1>My Service Offerings</h1>
    @foreach($services as $service)
        <p>{{ $service->title }} - ${{ $service->price }}</p>
    @endforeach
</div>
@endsection
