<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents photo evidence attached to a ticket accomplishment report.
 */
class AccomplishmentPhoto extends Model
{
    protected $fillable = [
        'accomplishment_id',
        'file_path',
    ];

    // --- RELATIONSHIPS ---

    /**
     * The accomplishment report this photo belongs to.
     */
    public function accomplishment(): BelongsTo
    {
        return $this->belongsTo(TicketAccomplishment::class, 'accomplishment_id');
    }
}
