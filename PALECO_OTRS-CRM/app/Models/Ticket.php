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
use Illuminate\Database\Eloquent\Relations\HasOne;
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
     * Currently active field team assignment for this ticket.
     */
    public function activeAssignment(): HasOne
    {
        return $this->hasOne(TicketAssignment::class, 'ticket_id')
            ->ofMany([
                'id' => 'max',
            ], function ($query) {
                $query->whereNull('unassigned_at');
            });
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

    // --- SCOPES (UNIFIED) ---

    /**
     * Unified scope: Search tickets across ticket number, complaint source, description,
     * address (purok, street, barangay), status, custom category, and category name.
     */
    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (empty($search)) {
            return $query;
        }

        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
                ->orWhere('complaint_source', 'like', "%{$search}%")
                ->orWhere('complaint_description', 'like', "%{$search}%")
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
     * Unified scope: Polymorphically filter tickets by category.
     * Supports:
     * - Numeric ID (e.g. 1 or '1' from Web Livewire dropdown)
     * - 'other' (custom classified tickets)
     * - Category name string (e.g. 'Power Interruption / Blackout' from Mobile API)
     * Gracefully ignores null, empty string, or 'all'.
     */
    public function scopeFilterByCategory(Builder $query, mixed $filter): Builder
    {
        if (empty($filter) || (is_string($filter) && strtolower($filter) === 'all')) {
            return $query;
        }

        if (is_string($filter) && strtolower($filter) === 'other') {
            return $query->where('other_category', true);
        }

        if (is_numeric($filter)) {
            return $query->where('category_id', (int) $filter);
        }

        return $query->whereHas('category', function ($q) use ($filter) {
            $q->where('category_name', $filter);
        });
    }

    /**
     * Unified scope: Filter tickets by lifecycle status.
     * Accepts TicketStatus enum or string representation. Gracefully ignores null, empty, or 'all'.
     */
    public function scopeFilterByStatus(Builder $query, mixed $status): Builder
    {
        if (empty($status) || (is_string($status) && strtolower($status) === 'all')) {
            return $query;
        }

        if ($status instanceof TicketStatus) {
            return $query->where('status', $status);
        }

        if (is_string($status)) {
            if ($validStatus = TicketStatus::tryFrom($status)) {
                return $query->where('status', $validStatus);
            }
        }

        return $query;
    }

    /**
     * Unified scope: Filter tickets by assigned department.
     * Supports:
     * - Numeric ID (e.g. 2 or '2' from Web dropdown)
     * - 'unassigned' (tickets without an assigned department)
     * - Department name string (e.g. 'Technical Services' from API)
     * Gracefully ignores null, empty string, or 'all'.
     */
    public function scopeFilterByDepartment(Builder $query, mixed $department): Builder
    {
        if (empty($department) || (is_string($department) && strtolower($department) === 'all')) {
            return $query;
        }

        if (is_string($department) && strtolower($department) === 'unassigned') {
            return $query->whereNull('department_id');
        }

        if (is_numeric($department)) {
            return $query->where('department_id', (int) $department);
        }

        return $query->whereHas('department', function ($q) use ($department) {
            $q->where('dept_name', $department);
        });
    }

    /**
     * Unified scope: Sort tickets by created_at date, ticket number, or status.
     */
    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'ticket_number_asc' => $query->orderBy('ticket_number', 'asc'),
            'ticket_number_desc' => $query->orderBy('ticket_number', 'desc'),
            'status' => $query->orderBy('status'),
            'oldest' => $query->oldest('created_at'),
            default => $query->latest('created_at'),
        };
    }

    // --- SCOPES (API ALIASES FOR BACKWARD COMPATIBILITY) ---

    /**
     * @deprecated Use scopeSearch instead.
     */
    public function scopeApiSearch(Builder $query, ?string $search): Builder
    {
        return $this->scopeSearch($query, $search);
    }

    /**
     * @deprecated Use scopeFilterByCategory instead.
     */
    public function scopeApiFilterByCategoryName(Builder $query, ?string $filter): Builder
    {
        return $this->scopeFilterByCategory($query, $filter);
    }

    /**
     * @deprecated Use scopeFilterByStatus instead.
     */
    public function scopeApiFilterByStatus(Builder $query, mixed $status): Builder
    {
        return $this->scopeFilterByStatus($query, $status);
    }

    /**
     * @deprecated Use scopeFilterByDepartment instead.
     */
    public function scopeApiFilterByDepartment(Builder $query, mixed $department): Builder
    {
        return $this->scopeFilterByDepartment($query, $department);
    }

    /**
     * @deprecated Use scopeSort instead.
     */
    public function scopeApiSort(Builder $query, ?string $sort): Builder
    {
        return $this->scopeSort($query, $sort);
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
