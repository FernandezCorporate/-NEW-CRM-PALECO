@php
    $layout = 'cwd.base.base';
    $cancelRoute = route('cwd.tickets.show', $ticket);
    $actionRoute = route('cwd.tickets.forceClose', $ticket);
@endphp

@extends($layout)

@section('workspace-kind', 'confirmation')

@section('title', 'Force Close Ticket - ' . $ticket->ticket_number)

@section('content')
    {{-- Shown if the ticket changes while this page is open; ForceCloseTicket still re-checks on submit --}}
    <div class="max-w-2xl mx-auto mt-6 -mb-4">
        <x-live-notice events="TicketForceClosed,TicketAccomplished,TicketVerifiedAndClosed,TicketEndorsementInitiated,TicketCreated,TicketCancelled" match="ticket_id:{{ $ticket->id }}|parent_ticket_id:{{ $ticket->id }}"
            message="This ticket or one of its child tickets just changed, so it may no longer be force-closable." />
    </div>

    <x-confirm-card
        title="Force Close Service Ticket?"
        :action="$actionRoute"
        method="POST"
        :cancel-route="$cancelRoute"
        back-label="Back to Ticket Details"
        confirm-text="Yes, Force Close Ticket"
        loading-text="Force Closing..."
        variant="warning"
        :consequences="[
            '<span class=\'font-bold\'>Active field crew assignments will be immediately terminated.</span>',
            'The ticket will be permanently marked as closed without lineman sign-off.',
            'All subordinate child tickets must already be closed or cancelled.',
            'An immutable administrative audit log entry will be created.'
        ]"
    >
        You are about to administratively force-close ticket <strong class="font-bold text-slate-900 font-mono">{{ $ticket->ticket_number }}</strong>.
        This bypasses standard field accomplishment verification. Please enter the operational justification below.

        <x-slot:formInputs>
            <div>
                <label for="closure_reason" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Closure Justification <span class="text-amber-600">*</span>
                </label>
                <textarea 
                    name="closure_reason" 
                    id="closure_reason" 
                    rows="3" 
                    required 
                    placeholder="Type the operational justification (e.g. consumer refused entry/signature, resolved by third-party, hazard prevents completion)..." 
                    class="form-input w-full rounded-lg border border-slate-300 text-sm p-3 bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none leading-relaxed placeholder:text-slate-400 @error('closure_reason') !border-amber-500 !ring-2 !ring-amber-500/20 @enderror">{{ old('closure_reason') }}</textarea>
                @error('closure_reason')
                    <p class="text-xs text-rose-500 mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </x-slot:formInputs>
    </x-confirm-card>
@endsection

