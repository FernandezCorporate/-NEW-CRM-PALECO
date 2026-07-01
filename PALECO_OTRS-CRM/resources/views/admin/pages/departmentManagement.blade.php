@extends('admin.base.base')

@section('title', 'Department Management')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800">Department Management</h1>
        <p class="text-sm text-slate-500 mt-1">Monitor and manage department information</p>
    </div>

    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        
        <form action="{{ route('admin.departments') }}" method="GET" class="flex flex-col md:flex-row gap-4 w-full md:w-auto">
            <div class="flex items-center gap-2">
                <input type="text" name="search" placeholder="Search departments..." value="{{ request('search') }}" class="border border-slate-200 p-2.5 rounded-lg text-sm focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 w-64">
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">Search</button>
                
                @if(request()->filled('search'))
                    <a href="{{ route('admin.departments', ['sort' => request('sort')]) }}" class="text-slate-400 hover:text-slate-600 text-sm underline pl-2">Clear</a>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <select name="sort" onchange="this.form.submit()" class="border border-slate-200 p-2.5 rounded-lg text-sm bg-white cursor-pointer focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500">
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Sort by Newest</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Sort by Oldest</option>
                    <option value="dept_nameASC" {{ request('sort') === 'dept_nameASC' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="dept_nameDESC" {{ request('sort') === 'dept_nameDESC' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="dept_descASC" {{ request('sort') === 'dept_descASC' ? 'selected' : '' }}>Description (A-Z)</option>
                    <option value="dept_descDESC" {{ request('sort') === 'dept_descDESC' ? 'selected' : '' }}>Description (Z-A)</option>
                </select>
                <noscript><button type="submit" class="bg-slate-500 text-white px-4 py-2 rounded">Sort</button></noscript>
            </div>
        </form>

        <div class="flex items-center border border-slate-200 rounded-lg overflow-hidden bg-white shadow-sm shrink-0">
            <button id="list-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="List View">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
            </button>
            <div class="w-px h-5 bg-slate-200"></div>
            <button id="card-view-btn" class="px-3 py-2 text-slate-400 hover:text-slate-600 transition-colors focus:outline-none" title="Card View">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
            </button>
        </div>
    </div>

    <div id="list-view-container" class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden mb-4">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                    <th class="px-6 py-4">Department Name</th>
                    <th class="px-6 py-4">Department Description</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($departments as $department)
                    <tr class="hover:bg-slate-50 transition-colors">
                        <td class="px-6 py-4 font-medium text-slate-800">{{ $department->dept_name }}</td>
                        <td class="px-6 py-4 text-slate-500 text-sm">{{ $department->dept_desc }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-8 text-center text-slate-400 text-sm">No departments found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div id="card-view-container" class="hidden grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-4">
        @forelse ($departments as $department)
            <div class="bg-white border border-slate-200 rounded-xl p-6 shadow-sm hover:shadow-md transition-shadow relative">                
                <h3 class="text-lg font-bold text-slate-800 pr-16">{{ $department->dept_name }}</h3>
                <p class="text-sm text-slate-500 mt-3 leading-relaxed">{{ $department->dept_desc }}</p>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 border-2 border-dashed border-slate-200 rounded-xl">
                No departments found.
            </div>
        @endforelse
    </div>

    <div class="mt-6">
        {{ $departments->onEachSide(0)->links() }}
    </div>
@endsection