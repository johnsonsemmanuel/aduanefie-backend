@extends('layouts.master')

@section('content')
<div class="container">
    <h1>Edit Service Category</h1>
    <form method="POST" action="{{ route('admin.service.categories.update', $category->id) }}">
        @csrf
        @method('PUT')
        <input type="text" name="name" value="{{ $category->name }}" required>
        <textarea name="description">{{ $category->description }}</textarea>
        <select name="status">
            <option value="1" {{ $category->status == 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ $category->status == 0 ? 'selected' : '' }}>Inactive</option>
        </select>
        <input type="number" name="sort_order" value="{{ $category->sort_order }}">
        <button type="submit">Update</button>
    </form>
</div>
@endsection
