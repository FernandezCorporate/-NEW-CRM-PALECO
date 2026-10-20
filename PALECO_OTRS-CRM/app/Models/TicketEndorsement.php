<?php

namespace App\Models;

use App\Enums\EndorsementStatus;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents a formal cross-department endorsement request for a ticket.
 */
class TicketEndorsement extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'ticket_id',
        'suggested_department_id',
        'old_department_id',
        'new_department_id',
        'child_ticket_id',
        'reason',
        'status',
        'pre_endorsement_status',
        'rejection_reason',
        'reviewed_by_id',
        'created_by_id',
        'reviewed_at',
    ];

    // --- CASTS ---

    /**
     * Defines attribute type casting.
     */
    protected function casts(): array
    {
        return [
            'status' => EndorsementStatus::class,
            'pre_endorsement_status' => TicketStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * The ticket under endorsement consideration.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    /**
     * The personnel who initiated this endorsement request.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * The officer who reviewed/decided this endorsement request.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * The suggested target department for this endorsement.
     */
    public function suggestedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'suggested_department_id')->withTrashed();
    }

    /**
     * The department the ticket belonged to when the endorsement was requested.
     */
    public function oldDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'old_department_id')->withTrashed();
    }

    /**
     * The department CWD transferred the work to on approval.
     */
    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'new_department_id')->withTrashed();
    }

    /**
     * The child ticket created in the new department when this endorsement was approved.
     */
    public function childTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'child_ticket_id');
    }

    // --- SCOPES ---

    /**
     * Search endorsements by ticket ID, ticket number, creator name, or suggested, from or to department.
     * Creator names are encrypted, so matching creators are resolved in memory first.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        $creatorIds = User::idsMatchingSearch($search);

        return $query->where(function ($query) use ($search, $creatorIds) {
            $query->where('ticket_id', 'like', "%{$search}%")
                ->orWhereHas('ticket', function ($ticketQuery) use ($search) {
                    $ticketQuery->where('ticket_number', 'like', "%{$search}%");
                })
                ->orWhereIn('created_by_id', $creatorIds)
                ->orWhereHas('suggestedDepartment', function ($deptQuery) use ($search) {
                    $deptQuery->where('dept_name', 'like', "%{$search}%");
                })
                ->orWhereHas('oldDepartment', function ($deptQuery) use ($search) {
                    $deptQuery->where('dept_name', 'like', "%{$search}%");
                })
                ->orWhereHas('newDepartment', function ($deptQuery) use ($search) {
                    $deptQuery->where('dept_name', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Filter endorsements by review status.
     */
    public function scopeFilterByStatus(Builder $query, ?string $filter): Builder
    {
        if (empty($filter) || $filter === 'all') {
            return $query;
        }

        return $query->where('status', $filter);
    }
}
