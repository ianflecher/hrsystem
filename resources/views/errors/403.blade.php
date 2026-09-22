@extends('errors.layout')

@section('title', 'Not yours to open')
@section('code', '403')
@section('heading', 'This one is not yours to open.')

@section('body')
    <p>
        You are signed in, but this page belongs to somebody else &mdash; another
        person's payslip, an interview assigned elsewhere, or a part of the back
        office your account does not cover.
    </p>
    <p>
        If you believe it should be yours, HR can check what your account is set to.
    </p>
@endsection

@section('foot', 'Being refused here is the system working, not failing.')
