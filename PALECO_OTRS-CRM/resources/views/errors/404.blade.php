@extends('errors.layout')

@section('title', '404 Page or Record Not Found')
@section('code', '404')
@section('heading', 'Page or Record Not Found')

@section('badge')
<div class="w-20 h-20 rounded-2xl bg-emerald-50 border border-emerald-200/80 flex items-center justify-center text-emerald-600 shadow-inner">
    <x-icons.search class="w-10 h-10" />
</div>
@endsection

@section('message')
    @php
        $msg = (isset($exception) && $exception->getMessage() && $exception->getMessage() !== 'Not Found' && !str_starts_with($exception->getMessage(), 'The route')) 
            ? $exception->getMessage() 
            : 'The page, service ticket, or resource you are trying to access does not exist, has been moved, or you do not have permission to view it.';
    @endphp
    {{ $msg }}
@endsection

