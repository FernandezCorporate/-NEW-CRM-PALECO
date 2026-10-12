@extends('errors.layout')

@section('title', '403 Access Forbidden')
@section('code', '403')
@section('heading', 'Access Forbidden')

@section('badge')
<div class="w-20 h-20 rounded-2xl bg-amber-50 border border-amber-200/80 flex items-center justify-center text-amber-600 shadow-inner">
    <x-icons.exclamation-triangle class="w-10 h-10" />
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

