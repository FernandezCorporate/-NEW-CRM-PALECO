@extends('errors.layout')

@section('title', '404 Page or Record Not Found')
@section('code', '404')
@section('heading', 'Page or Record Not Found')

@section('badge')
<div class="w-20 h-20 rounded-2xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-emerald-600 shadow-inner">
    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
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

