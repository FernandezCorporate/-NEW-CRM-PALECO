@extends('admin.base.base')

@section('workspace-kind', 'confirmation')

@section('title', $title)

@section('content')
    @php
        $cancelRoute = request('source') === 'details' 
            ? route('admin.ticketCategories.show', $category) 
            : session('category_list_url', route('admin.ticketCategories'));

        $consequences = $isForceDelete ? [
            '<span class=\'font-bold\'>This ticket category will be destroyed entirely and cannot be restored.</span>',
            'Only allowed for a category created by mistake: it is refused if any ticket has ever used it.',
            'Categories that were used should stay archived instead.'
        ] : [
            'The category will be hidden from future ticket creation.',
            'Historical tickets already using this category will retain their category link.',
            'You can restore this category later from the system archives.'
        ];
    @endphp

    <x-confirm-card
        :title="$title . '?'"
        :action="$isForceDelete ? route('admin.ticketCategories.destroy', $category) : route('admin.ticketCategories.archive', $category)"
        method="DELETE"
        :cancel-route="$cancelRoute"
        back-label="Back to Categories"
        :confirm-text="'Yes, ' . ($isForceDelete ? 'Permanently Delete' : 'Archive Category')"
        :loading-text="$isForceDelete ? 'Deleting...' : 'Archiving...'"
        variant="danger"
        :consequences="$consequences"
        :hidden-inputs="[
            'source' => request('source'),
        ]"
    >
        You are about to {{ $isForceDelete ? 'permanently delete' : 'archive' }} the <strong class="font-bold text-slate-900">"{{ $category->category_name }}"</strong> category. 
        @if($isForceDelete)
            This action is irreversible and cannot be undone.
        @else
            This will hide it from future ticket creation workflows.
        @endif
        Are you sure you want to proceed?
    </x-confirm-card>
@endsection
