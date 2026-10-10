<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Represents a core service ticket or complaint logged into the system.
 * Tracks location, categorization, assignments, and resolution states.
 */
class Ticket extends Model
{
    use Auditable, HasUlids, SoftDeletes;

    protected string $activityLogName = 'Tickets';

    protected string $activityTitleAttribute = 'ticket_number';

    protected array $activityLogAttributes = [
        'ticket_number',
        'consumer_contact',
        'complaint_source',
        'category_id',
        'other_category',
        'other_category_name',
        'barangay',
        'department_id',
        'team_id',
        'status',
        'started_at',
        'closed_at',
    ];

    protected $fillable = [
        'ticket_number',
        'parent_ticket_id',
        'consumer_id',
        'consumer_contact',
        'complaint_source',
        'complaint_description',
        'category_id',
        'other_category',
        'other_category_name',
        'purok',
        'street',
        'barangay',
        'landmark',
        'department_id',
        'team_id',
        'created_by_id',
        'status',
        'started_at',
        'reported_at',
        'resolved_at',
        'closed_at',
        'is_offline_synced',
        'synced_at',
        'client_started_at',
    ];

    // --- CASTS ---

    /**
     * Defines attribute type casting.
     */
    protected function casts(): array
    {
        return [
            'complaint_source' => ComplaintSources::class,
            'other_category' => 'boolean',
            'status' => TicketStatus::class,
            'started_at' => 'datetime',
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'is_offline_synced' => 'boolean',
            'synced_at' => 'datetime',
            'client_started_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    /**
     * The department currently assigned to resolve this ticket.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * The field team assigned to this ticket.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    /**
     * The category classification of the complaint.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /**
     * Historical audit log of status changes for this ticket.
     */
    public function statusLog(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class, 'ticket_id');
    }

    /**
     * The user who logged or created this ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * Child tickets spawned under this parent ticket.
     */
    public function childTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id');
    }

    /**
     * The parent ticket if this is an endorsed child ticket.
     */
    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    /**
     * Field team assignments recorded for this ticket.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'ticket_id');
    }

    /**
     * Accomplishment reports submitted for this ticket.
     */
    public function accomplishments(): HasMany
    {
        return $this->hasMany(TicketAccomplishment::class, 'ticket_id');
    }

    /**
     * Endorsement transfer requests initiated for this ticket.
     */
    public function endorsements(): HasMany
    {
        return $this->hasMany(TicketEndorsement::class, 'ticket_id');
    }

    /**
     * Timeline communication remarks added to this ticket.
     */
    public function remarks(): HasMany
    {
        return $this->hasMany(TicketRemark::class, 'ticket_id');
    }

    /**
     * The consumer linked to this ticket (if applicable).
     */
    public function consumer(): BelongsTo
    {
        return $this->belongsTo(Consumer::class, 'consumer_id', 'id');
    }

    // --- ACCESSORS & MUTATORS ---

    /**
     * Dynamically computed ticket subject header combining category and location.
     */
    protected function subject(): Attribute
    {
        return Attribute::make(
            get: function () {
                $purok = $this->purok ? Str::upper($this->purok).' ' : '';
                $street = $this->street ? Str::upper($this->street).' ' : '';
                $barangay = Str::upper($this->barangay);

                $completeAddress = trim(implode('', [$purok, $street, $barangay]));
                $categoryName = $this->category ? $this->category->category_name : $this->other_category_name;

                return ($categoryName ?? 'UNSPECIFIED').' @ '.($completeAddress ?? 'UNKNOWN');
            }
        );
    }

    // --- SCOPES (WEB) ---

    /**
     * Web scope: Search tickets by ticket number, description, barangay, or custom category.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('complaint_description', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%")
                ->orWhere('other_category_name', 'like', "%{$search}%");
        });
    }

    /**
     * Web scope: Filter tickets by category ID or custom 'other' classification.
     */
    public function scopeFilterByCategory(Builder $query, ?string $filter): Builder
    {
        if (empty($filter) || $filter === 'all') {
            return $query;
        }

        if ($filter === 'other') {
            return $query->where('other_category', true);
        }

        return $query->where('category_id', $filter);
    }

    /**
     * Web scope: Filter tickets by status enum value.
     */
    public function scopeFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status)) {
            return $query;
        }

        $validStatuses = array_column(TicketStatus::cases(), 'value');

        if (in_array($status, $validStatuses)) {
            return $query->where('status', $status);
        }

        return $query;
    }

    /**
     * Web scope: Sort tickets by date or status.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'status' => $query->orderBy('status'),
            default => $query->latest(),
        };
    }

    // --- SCOPES (API) ---

    /**
     * API scope: Multi-field search for mobile endpoints.
     */
    public function scopeApiSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('complaint_source', 'like', "%{$search}%")
                ->orWhere('purok', 'like', "%{$search}%")
                ->orWhere('street', 'like', "%{$search}%")
                ->orWhere('barangay', 'like', "%{$search}%")
                ->orWhere('status', 'like', "%{$search}%")
                ->orWhere('other_category_name', 'like', "%{$search}%")
                ->orWhereHas('category', function ($catQuery) use ($search) {
                    $catQuery->where('category_name', 'like', "%{$search}%");
                });
        });
    }

    /**
     * API scope: Filter tickets by category name or 'other'.
     */
    public function scopeApiFilterByCategoryName(Builder $query, ?string $filter): Builder
    {
        if (empty($filter)) {
            return $query;
        }

        if (strtolower($filter) === 'other') {
            return $query->where('other_category', true);
        }

        return $query->whereHas('category', function ($q) use ($filter) {
            $q->where('category_name', $filter);
        });
    }

    /**
     * API scope: Filter tickets by validated lifecycle status.
     * Gracefully ignores 'all' and invalid status values.
     */
    public function scopeApiFilterByStatus(Builder $query, ?string $status): Builder
    {
        if (empty($status) || strtolower($status) === 'all') {
            return $query;
        }

        if ($validStatus = TicketStatus::tryFrom($status)) {
            return $query->where('status', $validStatus);
        }

        return $query;
    }

    /**
     * API scope: Sort tickets.
     */
    public function scopeApiSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'ticket_number_asc' => $query->orderBy('ticket_number', 'asc'),
            'ticket_number_desc' => $query->orderBy('ticket_number', 'desc'),
            'oldest' => $query->oldest('created_at'),
            default => $query->latest('created_at'),
        };
    }

    // --- ACTIVITY LOG CONFIGURATION ---

    /**
     * Custom description handler for ticket lifecycle milestones.
     * Uses wasChanged() to evaluate post-save attribute updates accurately.
     */
    protected function getCustomActivityDescription(string $eventName): ?string
    {
        if ($eventName === 'updated' && $this->wasChanged('team_id')) {
            $action = $this->getOriginal('team_id') === null ? 'assigned' : 'reassigned';
            if ($this->status !== TicketStatus::PENDING_ENDORSEMENT) {
                return "Ticket {$this->ticket_number} has been {$action} to a field team.";
            }
        }

        if ($eventName === 'updated' && $this->wasChanged('status')) {
            if ($this->getOriginal('status') === TicketStatus::PENDING_ENDORSEMENT && $this->status !== TicketStatus::ENDORSED) {
                return "The endorsement request was rejected. Ticket {$this->ticket_number} has been returned to its previous state.";
            }

            if ($this->status === TicketStatus::IN_PROGRESS) {
                if ($this->getOriginal('status') === TicketStatus::RESOLVED) {
                    return "The accomplishment report was rejected. Ticket {$this->ticket_number} has been returned to In Progress.";
                }

                return "Work has started on Ticket {$this->ticket_number}.";
            }

            if ($this->status === TicketStatus::PENDING_ENDORSEMENT) {
                return "An endorsement request was submitted. Ticket {$this->ticket_number} is pending management review.";
            }

            if ($this->status === TicketStatus::ENDORSED) {
                return "The endorsement request was approved. Ticket {$this->ticket_number} has been routed to a new department.";
            }

            if ($this->status === TicketStatus::RESOLVED) {
                return "An accomplishment report was submitted. Ticket {$this->ticket_number} is now resolved and pending verification.";
            }

            if ($this->status === TicketStatus::CLOSED) {
                return "Ticket {$this->ticket_number} has been verified and closed.";
            }
        }

        return null;
    }
}
