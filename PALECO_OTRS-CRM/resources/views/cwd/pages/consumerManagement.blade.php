@extends('cwd.base.base')

@section('title', 'Consumers')

@section('content')

    @include('cwd.prompts.alert')

    <!-- Page Header -->
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Consumers</h1>
            <p class="text-sm text-gray-500 mt-1">Member-consumer accounts and service history</p>
        </div>
    </div>

    <!-- Search & Filter Controls -->
    <div class="mb-8">
        <form action="{{ request()->url() }}" method="GET" class="flex flex-col lg:flex-row gap-4 items-center w-full">
            <div class="relative w-full flex-grow">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
                <input type="text" name="search" placeholder="Search by name or account ID..." value="{{ request('search') }}" class="w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-900 placeholder:text-gray-400 focus:outline-none focus:border-[#008f5d] focus:ring-1 focus:ring-[#008f5d] shadow-sm">
            </div>

            <div class="flex items-center gap-3 w-full lg:w-auto shrink-0">
                <button type="submit" class="queue-search" data-loading-text="Searching...">Search</button>
                @if(request()->filled('search'))
                    <a href="{{ request()->url() }}" class="queue-clear">Clear filters</a>
                @endif
            </div>
        </form>
    </div>

    <!-- Figma 3-Column Card Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">
        @forelse($consumers as $consumer)
            <div class="bg-white border border-gray-200 rounded-xl shadow-sm p-6 hover:shadow-md transition-shadow flex flex-col h-full">
                
                <!-- Card Header -->
                <div class="flex justify-between items-start mb-4">
                    <h3 class="text-lg font-bold text-gray-900 leading-tight pr-2">{{ $consumer->name }}</h3>
                    
                    @if($consumer->status === 'A')
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 tracking-wide shrink-0">Active</span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200 tracking-wide shrink-0">Suspended</span>
                    @endif
                </div>

                <!-- Card Body -->
                <div class="space-y-3 mb-6 flex-grow">
                    <div class="text-xs text-gray-500 font-mono font-medium tracking-wide">{{ $consumer->acct_code }}</div>
                    
                    <div class="flex items-center gap-3 text-sm text-gray-700">
                        <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        <span>{{ $consumer->contact_number ?? 'No contact provided' }}</span>
                    </div>

                    <div class="flex items-start gap-3 text-sm text-gray-700">
                        <svg class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span class="line-clamp-2 leading-relaxed">{{ $consumer->address }}</span>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="pt-4 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-sm text-gray-500">Open tickets: <strong class="text-gray-900 ml-1">{{ $consumer->open_tickets_count ?? 0 }}</strong></span>
                </div>
            </div>
        @empty
            <div class="col-span-1 md:col-span-2 xl:col-span-3 py-16 text-center border-2 border-dashed border-gray-200 rounded-xl bg-gray-50">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-white border border-gray-200 mb-3 shadow-sm">
                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <h3 class="text-sm font-bold text-gray-900">No consumers found</h3>
                <p class="text-xs text-gray-500 mt-1">There are currently no consumer profiles matching your search criteria.</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($consumers->hasPages())
        <div class="mt-4">
            {{ $consumers->links() }}
        </div>
    @endif

@endsection