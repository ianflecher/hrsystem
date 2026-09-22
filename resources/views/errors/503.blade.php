@extends('errors.layout')

@section('title', 'Back shortly')
@section('code', '503')
@section('heading', 'Down for a few minutes.')

@section('body')
    <p>
        The system is being updated. It is deliberate, it is brief, and nothing is
        lost while it happens.
    </p>
@endsection

@section('foot', 'Try again in a minute or two.')
