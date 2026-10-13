@extends('cwd.base.base')

@section('workspace-kind', 'confirmation')

@section('title', 'Cancel Ticket - ' . $ticket->ticket_number)

@section('content')
    <x-confirm-card
        title="Cancel Service Ticket?"
        :action="route('cwd.tickets.cancel', $ticket)"
        method="POST"
        :cancel-route="route('cwd.tickets.show', $ticket)"
        back-label="Back to Ticket Details"
        confirm-text="Yes, Cancel Ticket"
        loading-text="Cancelling..."
        variant="danger"
        :consequences="[
            '<span class=\'font-bold\'>This ticket will be permanently closed as cancelled.</span>',
            'Field personnel will not be dispatched for this complaint.',
            'The operational cancellation reason will be permanently recorded on the ticket record.',
            'This action cannot be reversed once confirmed.'
        ]"
    >
        You are about to cancel open service ticket <strong class="font-bold text-slate-900 font-mono">{{ $ticket->ticket_number }}</strong>.
        Please provide the operational justification for cancelling this intake request before proceeding.

        <x-slot:formInputs>
            <div>
                <label for="cancellation_reason" class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                    Reason for Cancellation <span class="text-rose-500">*</span>
                </label>
                <textarea 
                    name="cancellation_reason" 
                    id="cancellation_reason" 
                    rows="3" 
                    required 
                    placeholder="Type the cancellation reason (e.g. consumer called back stating power restored, duplicate ticket, invalid address)..." 
                    class="form-input w-full rounded-lg border border-slate-300 text-sm p-3 bg-white focus:border-rose-500 focus:ring-2 focus:ring-rose-200 outline-none leading-relaxed placeholder:text-slate-400 @error('cancellation_reason') !border-rose-500 !ring-2 !ring-rose-500/20 @enderror">{{ old('cancellation_reason') }}</textarea>
                @error('cancellation_reason')
                    <p class="text-xs text-rose-500 mt-1 font-medium">{{ $message }}</p>
                @enderror
            </div>
        </x-slot:formInputs>
    </x-confirm-card>
@endsection
