<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccomplishmentPhoto extends Model
{
    protected $fillable = [
        'accomplishment_id',
        'file_path',
    ];

    public function accomplishment(): BelongsTo
    {
        return $this->belongsTo(TicketAccomplishment::class, 'accomplishment_id');
    }
}