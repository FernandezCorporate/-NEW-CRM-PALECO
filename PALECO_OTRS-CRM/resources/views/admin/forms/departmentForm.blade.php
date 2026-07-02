@extends('admin.base.base')

@section('title', isset($dept) ? 'Edit Department' : 'Create New Department')

@section('content')
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-slate-800">{{ isset($dept) ? 'Edit Department' : 'Create New Department' }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ isset($dept) ? 'Update the details of this department.' : 'Add a new department to the cooperative structure.' }}</p>
    </div>

    @include('admin.prompts.alert')

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden max-w-2xl">
        <form action="{{ isset($dept) ? route('admin.departments.update', $dept) : route('admin.departments.store') }}" method="POST">
            @csrf

            @isset($dept)
                @method('PUT')
            @endisset
            
            <div class="p-6 space-y-6">
                <div>
                    <label for="dept_name" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Department Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="dept_name" name="dept_name" value="{{ old('dept_name', $dept->dept_name ?? '') }}" required 
                        class="w-full px-3.5 py-2.5 border rounded-lg text-sm transition-colors focus:outline-none focus:ring-4 focus:ring-emerald-500/10 
                        @error('dept_name') border-rose-300 focus:border-rose-500 @else border-slate-200 focus:border-emerald-500 @enderror">
                    
                    @error('dept_name')
                        <p class="mt-1.5 text-xs text-rose-500">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="dept_desc" class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Department Description
                    </label>
                    <textarea id="dept_desc" name="dept_desc" rows="4" 
                        class="w-full px-3.5 py-2.5 border border-slate-200 rounded-lg text-sm transition-colors focus:outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-500/10">{{ old('dept_desc', $dept->dept_desc ?? '') }}</textarea>
                    <p class="mt-1.5 text-[11px] text-slate-400">Optional. Briefly describe the responsibilities of this department.</p>
                </div>
            </div>

            <div class="bg-slate-50 px-6 py-4 border-t border-slate-200 flex items-center justify-end gap-3">
                <a href="{{ $backTo }}" class="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 hover:bg-slate-200 rounded-lg transition-colors">
                    Cancel
                </a>
                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-5 py-2 rounded-lg text-sm font-medium shadow-sm shadow-emerald-700/20 transition-all active:scale-[0.98]">
                    Save Department
                </button>
            </div>
            
        </form>
    </div>
@endsection