@extends('errors.layout')

@section('title', 'Something broke')
@section('code', '500')
@section('heading', 'Something broke at our end.')

@section('body')
    <p>
        This one is a fault, not a wrong turn. It has been logged with the time it
        happened, which is what anybody looking into it will need.
    </p>
    <p>
        If you were part-way through something, check whether it saved before doing
        it again.
    </p>
@endsection

@section('foot', 'Reported automatically. No need to send a screenshot, though it never hurts.')
