@extends('errors.layout')

@section('title', 'Session expired')
@section('code', '419')
@section('heading', 'You were away a while.')

@section('body')
    <p>
        The page sat open long enough for the session to expire, so what you just
        submitted was not accepted. Sign in again and it will go through.
    </p>
    <p>
        Nothing was saved, and nothing was half-saved.
    </p>
@endsection

@section('foot', 'A precaution against somebody else using a machine you left open.')
