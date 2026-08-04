<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Enums\TicketAccomplishmentStatus;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// --- Added Spatie Imports matching your pattern ---
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class TicketAccomplishment extends Model
{
    use LogsActivity; // Added Trait

    protected $fillable = [
        'ticket_id',
        'accomplished_by_id',
        'remarks',
        'accomplished_at',
        'signature_path',
        'consumer_name',
        'status',
        'rejected_by_id',
        'rejection_reason'
    ];

    protected function casts(): array
    {
        return [
            'accomplished_at' => 'datetime',
            'status' => TicketAccomplishmentStatus::class,
        ];
    }

    // --- RELATIONSHIPS ---

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id');
    }

    public function accomplishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accomplished_by_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by_id');
    }

    // --- ACTIVITY LOG ---

    /*
     * Configures the Spatie Activitylog options for this model.
     * Records when reports are submitted, approved, or rejected.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('Accomplishments')
            ->logOnly([
                'status',
                'remarks',
                'rejection_reason',
            ])
            ->logOnlyDirty()
            ->setDescriptionForEvent(function(string $eventName) {
                
                // 1. DYNAMIC STATUS LOGIC: Intercept when the Foreman verifies the report
                if ($eventName === 'updated' && $this->isDirty('status')) {
                    if ($this->status === TicketAccomplishmentStatus::REJECTED) {
                        return "Accomplishment report for this ticket was rejected.";
                    }
                    // Assuming you have an APPROVED or similar case in your enum
                    if ($this->status === TicketAccomplishmentStatus::APPROVED) {
                        return "Accomplishment report for this ticket was verified and approved.";
                    }
                }

                // 2. DEFAULT LOGIC: Handle standard state changes matching your Ticket pattern
                $action = match($eventName) {
                    'created'  => 'submitted',
                    'updated'  => 'modified',
                    'deleted'  => 'deleted',
                    default    => $eventName,
                };
                
                return "An accomplishment report has been {$action}.";
            });
    }
}