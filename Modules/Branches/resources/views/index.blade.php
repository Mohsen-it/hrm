@extends('branches::layouts.master')

@section('content')
    <h1>Hello World</h1>

    <p>Module: {!! config('branches.name') !!}</p>
@endsection
