<?php

namespace App\Models;

use App\Enums\EscalationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketEscalation extends Model
{
    use HasUlids;

    protected $fillable = [
        'ticket_id',
        'suggested_department_id',
        'reason',
        'status',
        'pre_escalation_status',
        'rejection_reason',
        'reviewed_by',
        'created_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EscalationStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'system_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function suggestedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'suggested_department_id');
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) return $query;

        return $query->where(function ($query) use ($search) {
            $query->where('ticket_id', 'like', "%{$search}%")
              ->orWhereHas('ticket', function ($ticketQuery) use ($search) {
                  $ticketQuery->where('ticket_number', 'like', "%{$search}%");
              })
              ->orWhereHas('creator', function ($creatorQuery) use ($search) {
                  $creatorQuery->where('first_name', 'like', "%{$search}%")
                               ->orWhere('last_name', 'like', "%{$search}%");
              })
              ->orWhereHas('suggestedDepartment', function ($deptQuery) use ($search) {
                  $deptQuery->where('dept_name', 'like', "%{$search}%");
              });
        });
    }

    public function scopeFilterByStatus($query, $filter)
    {
        // Intercept 'all' and return the un-filtered query
        if (empty($filter) || $filter === 'all') return $query;

        return $query->where('status', $filter);
    }
}