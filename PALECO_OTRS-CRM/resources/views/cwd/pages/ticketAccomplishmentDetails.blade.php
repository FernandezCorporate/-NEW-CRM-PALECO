@extends('cwd.base.base')

@section('title', 'Accomplishment Details - ' . $ticket->ticket_number)

@section('content')

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
            <a href="{{ route('cwd.tickets.show', $ticket) }}" class="p-2 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors text-gray-500" title="Back to Ticket">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900 flex items-center gap-2">
                    ACCOMPLISHMENT REPORT DETAIL VIEW
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    Link: <a href="{{ route('cwd.tickets.show', $ticket) }}" class="text-[#008f5d] hover:underline font-semibold">{{ $ticket->ticket_number }}</a> • Fieldworker: {{ $accomplishment->accomplishedBy->fullName ?? 'Unknown' }}
                </p>
            </div>
        </div>
    </div>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        
        <!-- Top Left: General Report Summary -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6">
            <h2 class="text-lg font-bold text-gray-900 mb-5">General Report Summary</h2>
            
            <div class="space-y-4">
                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Report By</span>
                    <div class="text-sm font-medium text-gray-800">{{ $accomplishment->accomplishedBy->fullName ?? 'Unknown Worker' }} (Field Team)</div>
                </div>
                
                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Accomplished At</span>
                    <div class="text-sm text-gray-800">{{ $accomplishment->accomplished_at->format('M d, Y, h:i A') }}</div>
                </div>

                <div>
                    <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Status</span>
                    @php
                        $statusColor = match($accomplishment->status->value) {
                            'approved' => 'bg-emerald-100 text-emerald-700',
                            'rejected' => 'bg-red-100 text-red-700',
                            default => 'bg-amber-100 text-amber-700'
                        };
                    @endphp
                    <span class="inline-flex items-center justify-center px-2.5 py-1 rounded text-[11px] font-bold uppercase tracking-wider {{ $statusColor }}">
                        {{ $accomplishment->status->name }}
                    </span>
                </div>

                @if($accomplishment->status->value === 'approved' && $accomplishment->approvedBy)
                    <div>
                        <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Reviewed By</span>
                        <div class="text-sm text-gray-800">{{ $accomplishment->approvedBy->fullName }}</div>
                    </div>
                @elseif($accomplishment->status->value === 'rejected' && $accomplishment->rejectedBy)
                    <div class="p-3 bg-red-50 border border-red-100 rounded-lg">
                        <span class="block text-xs font-semibold text-red-400 uppercase tracking-wider mb-1">Rejected By: {{ $accomplishment->rejectedBy->fullName }}</span>
                        <div class="text-sm text-red-800 font-medium">Reason: {{ $accomplishment->rejection_reason }}</div>
                    </div>
                @endif
            </div>
        </div>

        <!-- Top Right: Worker Remarks -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 h-full">
            <h2 class="text-lg font-bold text-gray-900 mb-5">Worker Remarks</h2>
            
            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Detailed Remarks</span>
            <div class="text-sm text-gray-800 bg-gray-50 p-4 rounded-lg border border-gray-100 leading-relaxed whitespace-pre-wrap max-h-48 overflow-y-auto">
                {{ $accomplishment->remarks }}
            </div>
        </div>

        <!-- Bottom Left: Consumer Verification -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 h-full">
            <h2 class="text-lg font-bold text-gray-900 mb-5">Consumer Verification</h2>
            
            <div class="mb-4">
                <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-1">Consumer Name</span>
                <div class="text-sm font-medium text-gray-800">{{ $accomplishment->consumer_name ?? 'Not Provided' }}</div>
            </div>

            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Consumer Signature Image Frame</span>
            <div class="border-2 border-dashed border-gray-200 rounded-lg p-2 bg-gray-50 flex items-center justify-center min-h-[150px]">
                @if($accomplishment->signature_path)
                    <img src="{{ asset('storage/' . $accomplishment->signature_path) }}" alt="Consumer Signature" class="max-h-32 object-contain mix-blend-multiply">
                @else
                    <span class="text-sm text-gray-400 italic">No signature captured</span>
                @endif
            </div>
        </div>

        <!-- Bottom Right: Photo Evidence Gallery -->
        <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 h-full flex flex-col">
            <div class="flex justify-between items-center mb-5">
                <h2 class="text-lg font-bold text-gray-900">Photo Evidence Gallery</h2>
                <span class="text-xs font-bold text-gray-500 bg-gray-100 px-2 py-1 rounded">Photos: [{{ $accomplishment->photos->count() }}]</span>
            </div>

            <span class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Proof-of-Work</span>
            
            @if($accomplishment->photos->isNotEmpty())
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 overflow-y-auto max-h-[600px] pr-2 pb-2">
                    @foreach($accomplishment->photos as $index => $photo)
                        <!-- Added js-lightbox-trigger class and data-image-src attribute for modular JS -->
                        <div class="group relative rounded-lg border border-gray-200 overflow-hidden bg-gray-800 aspect-[4/3] shadow-sm js-lightbox-trigger cursor-pointer" 
                             data-image-src="{{ asset('storage/' . $photo->file_path) }}">
                            <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Evidence {{ $index + 1 }}" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105 pointer-events-none">
                            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/80 to-transparent pt-8 pb-3 px-4 pointer-events-none">
                                <span class="text-sm text-white font-bold block drop-shadow-md">Evidence {{ $index + 1 }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex-grow flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-lg bg-gray-50 text-gray-400 p-6">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span class="text-sm italic">No photo evidence uploaded</span>
                </div>
            @endif
        </div>
    </div>

    <!-- Fullscreen Lightbox Overlay (Hidden by default) -->
    <div id="image-lightbox" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/95 p-4 sm:p-8 backdrop-blur-sm js-lightbox-close">
        
        <!-- Close Button -->
        <button type="button" class="absolute top-6 right-8 text-white/70 hover:text-white transition-colors z-[60] js-lightbox-close">
            <svg class="w-10 h-10 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <!-- Dynamic Image container -->
        <div class="relative w-full h-full flex items-center justify-center overflow-hidden">
            <img id="lightbox-image" 
                 src="" 
                 alt="Full Screen Evidence" 
                 class="max-w-full max-h-full object-contain cursor-zoom-in transition-transform duration-300 ease-out origin-center">
        </div>
    </div>
@endsection