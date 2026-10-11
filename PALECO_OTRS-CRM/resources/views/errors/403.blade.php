@extends('errors.layout')

@section('title', '403 Access Forbidden')
@section('code', '403')
@section('heading', 'Access Forbidden')

@section('badge')
<div class="w-20 h-20 rounded-2xl bg-amber-50 border border-amber-100 flex items-center justify-center text-amber-600 shadow-inner">
    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
    </svg>
</div>
@endsection

@section('message')
    @php
        $msg = (isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'This action is unauthorized.') 
            ? $exception->getMessage() 
            : 'You do not have the required administrative role or privileges to access this protected workspace.';
    @endphp
    {{ $msg }}
@endsection

