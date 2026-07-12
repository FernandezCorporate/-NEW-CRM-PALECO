<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Department;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;
use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;

class Ticket extends Model
{
    use LogsActivity, SoftDeletes, HasUlids;
    
    protected function casts(): array
    {
        return [
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
        return $this->hasMany(TicketStatusLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentTicket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'parent_ticket_id');
    }

    public function childTicket(): HasMany
    {
        return $this->hasMany(Ticket::class, 'parent_ticket_id');
    }

    protected function subject(): Attribute
    {
        return Attribute::make(
            get: function () {
                $purok = Str::upper($this?->purok);
                $street = Str::upper($this?->street);
                $barangay = Str::upper($this?->barangay);
                
                $completeAddress = implode(' ', array_filter([$purok, $street, $barangay]));
                $category = $this->category ? $this->category->category_name : $this->other_category_name;

                return $category . '@' . $completeAddress;
            }
        );
    }
}
