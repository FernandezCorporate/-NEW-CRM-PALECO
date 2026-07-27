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
        'reported_at',
        'resolved_at'
    ];

    // --- CASTS ---

    /*
     * Defines Enum and datetime casting for accurate data formatting.
     */
    protected function casts(): array
    {
        return [
            'complaint_source' => ComplaintSources::class,
            'other_category' => 'boolean',
            'status' => TicketStatus::class,
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    // --- RELATIONSHIPS ---

    /*
     * Retrieves the department assigned to resolve this ticket.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /*
     * Retrieves the system category assigned to this ticket.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'category_id');
    }

    /*
     * Retrieves the chronological history of status changes for this ticket.
     */
    public function statusLog(): HasMany
    {
        return $this->hasMany(TicketStatusLog::class, 'ticket_id', 'system_id');
    }

    /*
     * Retrieves the system user (CWD Officer) who originally created the ticket.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /*
     * Retrieves any child tickets spawned from this parent ticket.
     */
    public function childTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id', 'system_id');
    }

    // --- ACCESSORS ---

    /*
     * Generates a readable subject line combining the category and full address.
     */
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

    /*
     * Applies a search filter against the ticket number, description, and location.
     */
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

    /*
     * Applies a filter to isolate tickets belonging to a specific category or the 'other' classification.
     */
    public function scopeFilterByCategory($query, $filter)
    {
        if (empty($filter) || $filter === 'all') return $query;

        if ($filter === 'other') {
            return $query->where('other_category', true);
        }

        return $query->where('category_id', $filter);
    }

    /*
     * Applies sorting rules to the query based on chronological or status priorities.
     */
    public function scopeSort($query, $sort)
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'status' => $query->orderBy('status'),
            default => $query->latest(), // 'newest' is default
        };
    }

    // --- MOBILE API SCOPE FUNCTIONS ---

    /*
     * Applies a comprehensive search filter against fields specifically returned in the API payload.
     */
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

    /*
     * Applies a filter isolating tickets based on the exact category name or the 'other' classification.
     */
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

    /*
     * Applies a strict status match filter for the API list view.
     */
    public function scopeApiFilterByStatus($query, $status)
    {
        if (empty($status)) return $query;

        return $query->where('status', $status);
    }

    /*
     * Applies precise sorting rules for the mobile interface prioritizing ticket numbers and chronology.
     */
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

    /*
     * Configures the Spatie Activitylog options for this model.
     * Records critical changes to ticket configurations and status updates.
     */
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
                'status'
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(fn(string $eventName) => "Ticket {$this->ticket_number} has been {$eventName} by CWD Officer.");
    }
}