@php
    // Create: every permission is rendered and the script shows those of the picked role. Edit: only the account's role.
    $roleSlug = $user?->role?->slug_identifier;
    $items = $user ? $permissionCatalog->filter(fn ($meta) => in_array($roleSlug, $meta['roles'], true)) : $permissionCatalog;
    $groups = $items->groupBy('module', preserveKeys: true);

    $editable = $canManagePermissions;
    $submittedBefore = old('permissions_submitted');
    $checked = $submittedBefore ? old('permissions', []) : ($heldPermissions ?? null);
    $isAdminAccount = $roleSlug === 'admin';
@endphp

@if ($user || $editable)
    <fieldset class="space-y-5" id="permission-checklist" data-editable="{{ $editable ? 'true' : 'false' }}" data-mode="{{ $user ? 'edit' : 'create' }}" data-restore-old="{{ $submittedBefore ? 'true' : 'false' }}">
        <legend class="sr-only">Permissions</legend>
        <div class="border-b border-slate-200 pb-2 flex items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-slate-900 flex items-center gap-2">
                <x-icons.lock-closed class="w-4 h-4 text-emerald-600" />
                <span>Permissions</span>
            </h2>
            @if ($editable)
                <button type="button" id="permission-reset" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800">Reset to role defaults</button>
            @endif
        </div>

        @if ($editable)
            <input type="hidden" name="permissions_submitted" value="1">
            <p class="text-xs text-slate-500" id="permission-hint">
                {{ $user ? 'Untick an action to remove it from this account. Changes apply on the account\'s next request.' : 'Choose a role first. Its default permissions are ticked; untick any action this account should not perform.' }}
            </p>
            <p id="permission-admin-warning" class="hidden rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs font-medium text-amber-800">
                Administrator permissions cannot be changed after the account is created, and you can only give permissions that you have yourself.
            </p>
        @else
            <p class="rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs text-slate-600">
                @if ($isAdminAccount)
                    Administrator permissions are set when the account is created and cannot be changed afterwards.
                @else
                    You do not have permission to change the permissions of other accounts.
                @endif
            </p>
        @endif

        <x-form.error name="permissions" />

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4" id="permission-groups">
            @foreach ($groups as $module => $permissions)
                <div class="rounded-lg border border-slate-200 p-3" data-permission-group>
                    <p class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">{{ $module }}</p>
                    <ul class="space-y-1.5">
                        @foreach ($permissions as $name => $meta)
                            @php
                                $isChecked = $checked === null ? true : in_array($name, $checked, true);
                            @endphp
                            <li data-permission-item data-roles="{{ implode(' ', $meta['roles']) }}">
                                <label class="inline-flex items-start gap-2 text-sm text-slate-700 {{ $editable ? 'cursor-pointer' : 'cursor-default' }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $name }}"
                                        class="mt-0.5 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 disabled:opacity-60"
                                        {{ $isChecked ? 'checked' : '' }} {{ $editable ? '' : 'disabled' }}>
                                    <span>{{ $meta['label'] }}</span>
                                </label>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </fieldset>
@endif
