@extends('layouts.master')

@section('content')
<div class="container">
    <h1>Service Categories</h1>
    @foreach($categories as $category)
        <p>{{ $category->name }} - {{ $category->status == 1 ? 'Active' : 'Inactive' }}</p>
    @endforeach
</div>
@endsection
