<?php

/*
|--------------------------------------------------------------------------
| User Permissions
|--------------------------------------------------------------------------
| Every action a user can be allowed or refused, grouped by module.
| "roles" lists the account roles the permission applies to: those roles see it
| in the permission checklist, and new accounts of those roles receive it by default.
| A role still decides the portal and the workflow; a permission only narrows what
| a user of that role may do.
*/

return [
    // --- ACCOUNTS ---
    'users.view' => ['label' => 'View user accounts', 'module' => 'User Accounts', 'roles' => ['admin']],
    'users.create' => ['label' => 'Create user accounts', 'module' => 'User Accounts', 'roles' => ['admin']],
    'users.update' => ['label' => 'Edit accounts, reset passwords and unlock accounts', 'module' => 'User Accounts', 'roles' => ['admin']],
    'users.deactivate' => ['label' => 'Deactivate and reactivate accounts', 'module' => 'User Accounts', 'roles' => ['admin']],
    'users.permissions' => ['label' => 'Set the permissions of other accounts', 'module' => 'User Accounts', 'roles' => ['admin']],

    // --- DEPARTMENTS ---
    'departments.view' => ['label' => 'View departments', 'module' => 'Departments', 'roles' => ['admin']],
    'departments.create' => ['label' => 'Create departments', 'module' => 'Departments', 'roles' => ['admin']],
    'departments.update' => ['label' => 'Edit departments', 'module' => 'Departments', 'roles' => ['admin']],
    'departments.archive' => ['label' => 'Archive and restore departments', 'module' => 'Departments', 'roles' => ['admin']],
    'departments.delete' => ['label' => 'Permanently delete departments', 'module' => 'Departments', 'roles' => ['admin']],

    // --- TEAMS ---
    'teams.view' => ['label' => 'View teams', 'module' => 'Teams', 'roles' => ['admin', 'supervisor']],
    'teams.create' => ['label' => 'Create teams', 'module' => 'Teams', 'roles' => ['admin', 'supervisor']],
    'teams.update' => ['label' => 'Edit teams and their members', 'module' => 'Teams', 'roles' => ['admin', 'supervisor']],
    'teams.archive' => ['label' => 'Archive and restore teams', 'module' => 'Teams', 'roles' => ['admin', 'supervisor']],
    'teams.delete' => ['label' => 'Permanently delete teams', 'module' => 'Teams', 'roles' => ['admin', 'supervisor']],

    // --- TICKET CATEGORIES ---
    'categories.view' => ['label' => 'View ticket categories', 'module' => 'Ticket Categories', 'roles' => ['admin']],
    'categories.create' => ['label' => 'Create ticket categories', 'module' => 'Ticket Categories', 'roles' => ['admin']],
    'categories.update' => ['label' => 'Edit ticket categories', 'module' => 'Ticket Categories', 'roles' => ['admin']],
    'categories.archive' => ['label' => 'Archive and restore ticket categories', 'module' => 'Ticket Categories', 'roles' => ['admin']],
    'categories.delete' => ['label' => 'Permanently delete ticket categories', 'module' => 'Ticket Categories', 'roles' => ['admin']],

    // --- TICKETS ---
    'tickets.view' => ['label' => 'View tickets', 'module' => 'Tickets', 'roles' => ['admin', 'cwd_officer']],
    'tickets.create' => ['label' => 'Create tickets and child tickets', 'module' => 'Tickets', 'roles' => ['cwd_officer']],
    'tickets.update' => ['label' => 'Edit ticket intake details', 'module' => 'Tickets', 'roles' => ['cwd_officer']],
    'tickets.cancel' => ['label' => 'Cancel tickets', 'module' => 'Tickets', 'roles' => ['cwd_officer']],
    'tickets.force_close' => ['label' => 'Force-close tickets', 'module' => 'Tickets', 'roles' => ['cwd_officer']],
    'tickets.remark' => ['label' => 'Add remarks to tickets', 'module' => 'Tickets', 'roles' => ['cwd_officer', 'supervisor', 'field_personnel']],
    'tickets.assign' => ['label' => 'Assign and reassign tickets to teams', 'module' => 'Tickets', 'roles' => ['supervisor']],
    'tickets.endorse' => ['label' => 'Endorse tickets to the CWD', 'module' => 'Tickets', 'roles' => ['supervisor']],
    'tickets.history' => ['label' => 'View ticket history', 'module' => 'Tickets', 'roles' => ['supervisor']],
    'tickets.start' => ['label' => 'Start assigned tickets', 'module' => 'Tickets', 'roles' => ['field_personnel']],
    'tickets.accomplish' => ['label' => 'Submit accomplishment reports', 'module' => 'Tickets', 'roles' => ['field_personnel']],

    // --- ENDORSEMENTS & REPORTS ---
    'endorsements.view' => ['label' => 'View endorsement requests', 'module' => 'Endorsements and Reports', 'roles' => ['cwd_officer']],
    'endorsements.decide' => ['label' => 'Approve or reject endorsement requests', 'module' => 'Endorsements and Reports', 'roles' => ['cwd_officer']],
    'reports.view' => ['label' => 'View accomplishment reports', 'module' => 'Endorsements and Reports', 'roles' => ['cwd_officer']],
    'reports.verify' => ['label' => 'Approve or reject accomplishment reports', 'module' => 'Endorsements and Reports', 'roles' => ['supervisor']],

    // --- CONSUMERS & MONITORING ---
    'consumers.view' => ['label' => 'View consumer profiles', 'module' => 'Consumers and Monitoring', 'roles' => ['admin', 'cwd_officer']],
    'audit.view' => ['label' => 'View System Monitoring (activity log)', 'module' => 'Consumers and Monitoring', 'roles' => ['admin']],
];
