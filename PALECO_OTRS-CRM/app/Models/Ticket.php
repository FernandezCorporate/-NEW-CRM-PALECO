<?php

namespace App\Models;

use App\Enums\ComplaintSources;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
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

    public function scopeSort($query, $sort)
    {
        return match ($sort) {
            'oldest' => $query->oldest(),
            'status' => $query->orderBy('status'),
            default => $query->latest(), // 'newest' is default
        };
    }
}