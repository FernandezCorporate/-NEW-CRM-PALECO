@if (session('success'))
    <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 rounded-r-lg flex items-start gap-3 shadow-sm">
        <svg class="w-5 h-5 text-emerald-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-sm text-emerald-800 font-medium">{{ session('success') }}</div>
    </div>
@endif

@if (session('error') || $errors->any())
    <div class="mb-6 p-4 bg-rose-50 border-l-4 border-rose-500 rounded-r-lg flex items-start gap-3 shadow-sm">
        <svg class="w-5 h-5 text-rose-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div class="text-sm text-rose-800">
            @if(session('error'))
                <span class="font-medium">{{ session('error') }}</span>
            @else
                <span class="font-medium">Please correct the following errors:</span>
                <ul class="list-disc list-inside mt-1 ml-1 opacity-90">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif