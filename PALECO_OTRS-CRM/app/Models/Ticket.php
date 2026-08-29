<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;

use App\Models\Department;
use App\Models\TicketCategory;
use App\Models\TicketStatusLog;
use App\Models\User;

/*
 * Represents a core service ticket or complaint logged into the system.
 * Tracks location, categorization, assignments, and resolution states.
 */
class Ticket extends Model
{
    use LogsActivity, SoftDeletes, HasUlids;

    protected $primaryKey = 'system_id';

    protected $fillable = [
        'ticket_number',
        'parent_ticket_id',
        'consumer_id',
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
        'created_by',
        'status',
        'started_at',
        'reported_at',
        'resolved_at',
        'closed_at',
    ];

    // --- CASTS ---

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
        ];
    }

    // --- RELATIONSHIPS ---

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    public function statusLog(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class, 'ticket_id', 'system_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function childTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id', 'system_id');
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id', 'system_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TicketAssignment::class, 'ticket_id', 'system_id');
    }

    public function accomplishments(): HasMany
    {
        return $this->hasMany(TicketAccomplishment::class, 'ticket_id', 'system_id');
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(TicketEscalation::class, 'ticket_id', 'system_id');
    }

    // --- ACCESSORS ---

    protected function subject(): Attribute
    {
        return Attribute::make(
            get: function () {
                $purok = $this->purok ? Str::upper($this->purok) . ' ' : '';
                $street = $this->street ? Str::upper($this->street) . ' ' : '';
                $barangay = Str::upper($this->barangay);
                
                $completeAddress = trim(implode('', [$purok, $street, $barangay]));
                $categoryName = $this->category ? $this->category->category_name : $this->other_category_name;

                return ($categoryName ?? 'UNSPECIFIED') . ' @ ' . ($completeAddress ?? 'UNKNOWN');
            }
        );
    }

    // --- SCOPE FUNCTIONS ---

    public function scopeSearch($query, $search)
    {
        if (empty($search)) return $query;

        return $query->where(function ($q) use ($search) {
            $q->where('ticket_number', 'like', "%{$search}%")
              ->orWhere('complaint_description', 'like', "%{$search}%")
              ->orWhere('barangay', 'like', "%{$search}%")
              ->orWhere('other_category_name', 'like', "%{$search}%");
        });
    }

    public function scopeFilterByCategory($query, $filter)
    {
        if (empty($filter) || $filter === 'all') return $query;

        if ($filter === 'other') {
            return $query->where('other_category', true);
        }

        return $query->where('category_id', $filter);
    }

    public function scopeFilterByStatus($query, $status)
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

    public function scopeSort($query, $sort)
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'status' => $query->orderBy('status'),
            default => $query->latest(),
        };
    }

    // --- MOBILE API SCOPE FUNCTIONS ---

    public function scopeApiSearch($query, $search)
    {
        if (empty($search)) return $query;

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

    public function scopeApiFilterByCategoryName($query, $filter)
    {
        if (empty($filter)) return $query;

        if (strtolower($filter) === 'other') {
            return $query->where('other_category', true);
        }

        return $query->whereHas('category', function ($q) use ($filter) {
            $q->where('category_name', $filter);
        });
    }

    public function scopeApiFilterByStatus($query, $status)
    {
        if (empty($status)) return $query;

        return $query->where('status', $status);
    }

    public function scopeApiSort($query, $sort)
    {
        return match ($sort) {
            'ticket_number_asc'  => $query->orderBy('ticket_number', 'asc'),
            'ticket_number_desc' => $query->orderBy('ticket_number', 'desc'),
            'oldest'             => $query->oldest('created_at'),
            default              => $query->latest('created_at'),
        };
    }

    // --- ACTIVITY LOG ---

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Tickets')
            ->logOnly([
                'ticket_number',
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
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function(string $eventName) {
                
                if ($eventName === 'updated' && $this->isDirty('team_id')) {
                    $action = $this->getOriginal('team_id') === null ? 'assigned' : 'reassigned';
                    // Do not log team changes if the ticket is being escalated/unassigned
                    if ($this->status !== TicketStatus::PENDING_ESCALATION) {
                        return "Ticket {$this->ticket_number} has been {$action} to a field team.";
                    }
                }

                if ($eventName === 'updated' && $this->isDirty('status')) {
                    
                    // 1. Check for Rejections first (Intercepts reverting to ANY previous state)
                    if ($this->getOriginal('status') === TicketStatus::PENDING_ESCALATION && $this->status !== TicketStatus::ESCALATED) {
                        return "The escalation request was rejected. Ticket {$this->ticket_number} has been returned to its previous state.";
                    }

                    // 2. Then proceed with normal state-specific logs
                    if ($this->status === TicketStatus::IN_PROGRESS) {
                        if ($this->getOriginal('status') === TicketStatus::RESOLVED) {
                            return "The accomplishment report was rejected. Ticket {$this->ticket_number} has been returned to In Progress.";
                        }
                        return "Work has started on Ticket {$this->ticket_number}.";
                    }

                    if ($this->status === TicketStatus::PENDING_ESCALATION) {
                        return "An escalation request was submitted. Ticket {$this->ticket_number} is pending management review.";
                    }

                    if ($this->status === TicketStatus::ESCALATED) {
                        return "The escalation request was approved. Ticket {$this->ticket_number} has been routed to a new department.";
                    }

                    if ($this->status === TicketStatus::RESOLVED) {
                        return "An accomplishment report was submitted. Ticket {$this->ticket_number} is now resolved and pending verification.";
                    }

                    if ($this->status === TicketStatus::CLOSED) {
                        return "Ticket {$this->ticket_number} has been verified and closed.";
                    }
                }

                $action = match($eventName) {
                    'created'  => 'created',
                    'updated'  => 'modified',
                    'deleted'  => $this->isForceDeleting() ? 'permanently deleted' : 'archived',
                    'restored' => 'restored',
                    default    => $eventName,
                };
                
                return "Ticket {$this->ticket_number} has been {$action}.";
            });
    }
}