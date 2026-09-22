@extends('errors.layout')

@section('title', 'Page not found')
@section('code', '404')
@section('heading', 'That page is not here.')

@section('body')
    <p>
        The address may be mistyped, or the thing it pointed at has since been
        removed &mdash; a job opening that closed, or a record somebody deleted.
    </p>
@endsection

@section('foot', 'Nothing is broken; the page simply does not exist.')
