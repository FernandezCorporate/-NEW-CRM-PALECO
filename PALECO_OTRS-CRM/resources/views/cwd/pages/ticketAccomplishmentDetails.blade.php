@extends('cwd.base.base')

@section('workspace-kind', 'detail')

@section('title', 'Accomplishment Details - ' . $ticket->ticket_number)

@section('content')

    <!-- Header Section -->
    <header class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div class="flex items-center gap-4">
            @php
                $ticketRoute = route('cwd.tickets.show', array_filter(['ticket' => $ticket, 'source' => request('source'), 'consumer' => request('consumer'), 'parent' => request('parent')]));
            @endphp
            <x-contextual-back :url="$ticketRoute" label="Back to Ticket" variant="icon-box" class="min-h-10 min-w-10" />
            <div>
                <h1 class="text-2xl font-bold text-slate-900 flex items-center gap-2">
                    Accomplishment Report Details
                </h1>
                <p class="text-sm text-slate-500 mt-1">
                    Ticket: <a href="{{ $ticketRoute }}" class="text-emerald-700 hover:underline font-semibold">{{ $ticket->ticket_number }}</a> • Field Worker: {{ $accomplishment->accomplishedBy->fullName ?? 'Unknown' }}
                </p>
            </div>
        </div>
    </header>

    <!-- Main Grid Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
        
        <!-- Top Left: General Report Summary (Semantic Definition List) -->
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-6" aria-labelledby="summary-heading">
            <h2 id="summary-heading" class="text-base font-bold text-slate-900 mb-5">General Report Summary</h2>
            
            <dl class="space-y-4">
                <div>
                    <dt class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Reported By</dt>
                    <dd class="text-sm font-medium text-slate-800">{{ $accomplishment->accomplishedBy->fullName ?? 'Unknown Worker' }} (Field Team)</dd>
                </div>
                
                <div>
                    <dt class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Accomplished At</dt>
                    <dd class="text-sm text-slate-800">{{ $accomplishment->accomplished_at->format('M d, Y, h:i A') }}</dd>
                </div>

                <div>
                    <dt class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Status</dt>
                    <dd>
                        <span class="inline-flex items-center justify-center px-2.5 py-1 rounded-full border text-[11px] font-bold uppercase tracking-wider {{ $accomplishment->status->badgeClasses() }}">
                            {{ $accomplishment->status->label() }}
                        </span>
                    </dd>
                </div>

                @if($accomplishment->status->value === 'approved' && $accomplishment->approvedBy)
                    <div>
                        <dt class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Reviewed By</dt>
                        <dd class="text-sm text-slate-800">{{ $accomplishment->approvedBy->fullName }}</dd>
                    </div>
                @elseif($accomplishment->status->value === 'rejected' && $accomplishment->rejectedBy)
                    <div class="p-3 bg-rose-50 border border-rose-100 rounded-lg">
                        <dt class="block text-xs font-semibold text-rose-500 uppercase tracking-wider mb-1">Rejected By: {{ $accomplishment->rejectedBy->fullName }}</dt>
                        <dd class="text-sm text-rose-800 font-medium">Reason: {{ $accomplishment->rejection_reason }}</dd>
                    </div>
                @endif
            </dl>
        </section>

        <!-- Top Right: Worker Remarks -->
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 h-full" aria-labelledby="remarks-heading">
            <h2 id="remarks-heading" class="text-base font-bold text-slate-900 mb-5">Worker Remarks</h2>
            
            <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Detailed Remarks</span>
            <div class="text-sm text-slate-800 bg-slate-50 p-4 rounded-lg border border-slate-100 leading-relaxed whitespace-pre-wrap max-h-48 overflow-y-auto">
                {{ $accomplishment->remarks }}
            </div>
        </section>

        <!-- Bottom Left: Consumer Verification -->
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 h-full" aria-labelledby="verification-heading">
            <h2 id="verification-heading" class="text-base font-bold text-slate-900 mb-5">Consumer Verification</h2>
            
            <dl class="mb-4">
                <dt class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-1">Consumer Name</dt>
                <dd class="text-sm font-medium text-slate-800">{{ $accomplishment->consumer_name ?? 'Not Provided' }}</dd>
            </dl>

            <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Consumer Signature Image Frame</span>
            <div class="border-2 border-dashed border-slate-200 rounded-lg p-2 bg-slate-50 flex items-center justify-center min-h-[150px]">
                @if($accomplishment->signature_path)
                    <img src="{{ asset('storage/' . $accomplishment->signature_path) }}" alt="Consumer Signature" class="max-h-32 object-contain mix-blend-multiply">
                @else
                    <span class="text-sm text-slate-400 italic">No signature captured</span>
                @endif
            </div>
        </section>

        <!-- Bottom Right: Photo Evidence Gallery -->
        <section class="bg-white border border-slate-200 rounded-xl shadow-xs p-6 h-full flex flex-col" aria-labelledby="evidence-heading">
            <div class="flex justify-between items-center mb-5">
                <h2 id="evidence-heading" class="text-base font-bold text-slate-900">Photo Evidence Gallery</h2>
                <span class="text-xs font-bold text-slate-600 bg-slate-100 px-2 py-1 rounded">Photos: [{{ $accomplishment->photos->count() }}]</span>
            </div>

            <span class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-3">Proof-of-Work</span>
            
            @if($accomplishment->photos->isNotEmpty())
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6 overflow-y-auto max-h-[600px] pr-2 pb-2">
                    @foreach($accomplishment->photos as $index => $photo)
                        <button type="button" 
                             class="group relative rounded-lg border border-slate-200 overflow-hidden bg-slate-800 aspect-[4/3] shadow-xs js-lightbox-trigger cursor-pointer text-left focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2" 
                             data-image-src="{{ asset('storage/' . $photo->file_path) }}"
                             aria-label="View evidence photo {{ $index + 1 }} full screen">
                            <img src="{{ asset('storage/' . $photo->file_path) }}" alt="Evidence {{ $index + 1 }}" class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105 pointer-events-none">
                            <div class="absolute bottom-0 inset-x-0 bg-gradient-to-t from-black/80 to-transparent pt-8 pb-3 px-4 pointer-events-none">
                                <span class="text-sm text-white font-bold block drop-shadow-md">Evidence {{ $index + 1 }}</span>
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex-grow flex flex-col items-center justify-center border-2 border-dashed border-slate-200 rounded-lg bg-slate-50 text-slate-400 p-6">
                    <svg class="w-8 h-8 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    <span class="text-sm italic">No photo evidence uploaded</span>
                </div>
            @endif
        </section>
    </div>

    <!-- Fullscreen Lightbox Overlay (Accessible Modal Dialog) -->
    <div id="image-lightbox" 
         role="dialog" 
         aria-modal="true" 
         aria-label="Photo evidence inspection modal" 
         tabindex="-1"
         class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/95 p-4 sm:p-8 backdrop-blur-sm js-lightbox-close">
        
        <!-- Close Button -->
        <button type="button" 
                id="lightbox-close-btn"
                aria-label="Close photo inspection" 
                class="absolute top-6 right-8 text-white/70 hover:text-white transition-colors z-[60] js-lightbox-close p-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-white">
            <x-icons.x-mark class="w-8 h-8 pointer-events-none" />
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
