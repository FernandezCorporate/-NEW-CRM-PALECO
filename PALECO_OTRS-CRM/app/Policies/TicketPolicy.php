<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ticket;
use App\Enums\TicketStatus;

class TicketPolicy
{
    public function viewAny(User $user): bool { return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true); }
    public function webView(User $user, Ticket $ticket): bool { return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true); }
    public function ticketForm(User $user): bool { return $user->role->slug_identifier === 'cwd_officer'; }

    public function viewInbox(User $user): bool { return in_array($user->role->slug_identifier, ['foreman', 'field_personnel']); }
    public function assign(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $ticket->department_id; }

    public function view(User $user, Ticket $ticket): bool 
    { 
        if ($user->role->slug_identifier === 'foreman') {
            return $user->department_id === $ticket->department_id;
        }
        
        if ($user->role->slug_identifier === 'field_personnel') {
            return $user->teams()->where('teams.id', $ticket->team_id)->exists();
        }

        return in_array($user->role->slug_identifier, ['cwd_officer', 'admin'], true);
    }

    public function start(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'field_personnel' && $user->teams()->where('teams.id', $ticket->team_id)->exists(); }
    public function accomplish(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'field_personnel' && $user->teams()->where('teams.id', $ticket->team_id)->exists(); }
    public function verify(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $ticket->department_id; }
    public function escalate(User $user, Ticket $ticket): bool { return $user->role->slug_identifier === 'foreman' && $user->department_id === $ticket->department_id;}
    
    public function viewHistory(User $user, Ticket $ticket): bool {  
        if ($user->role->slug_identifier === 'foreman') {
            return $user->department_id === $ticket->department_id;
        }
        
        if ($user->role->slug_identifier === 'field_personnel') {
            return $user->teams()->where('teams.id', $ticket->team_id)->exists();
        }

        return false;
    }
}