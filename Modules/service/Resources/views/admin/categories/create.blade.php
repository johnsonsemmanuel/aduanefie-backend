@extends('layouts.master')

@section('content')
<div class="container">
    <h1>Create Service Category</h1>
    <form method="POST" action="{{ route('admin.service.categories.store') }}">
        @csrf
        <input type="text" name="name" placeholder="Category Name" required>
        <textarea name="description" placeholder="Description"></textarea>
        <select name="status">
            <option value="1">Active</option>
            <option value="0">Inactive</option>
        </select>
        <input type="number" name="sort_order" placeholder="Sort Order">
        <button type="submit">Create</button>
    </form>
</div>
@endsection
