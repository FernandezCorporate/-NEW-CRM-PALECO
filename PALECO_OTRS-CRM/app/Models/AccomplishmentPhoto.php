<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents photo evidence attached to a ticket accomplishment report.
 */
class AccomplishmentPhoto extends Model
{
    use HasFactory;
    protected $fillable = [
        'accomplishment_id',
        'file_path',
        'file_name',
        'file_size',
        'mime_type',
    ];

    // --- RELATIONSHIPS ---

    /**
     * The accomplishment report this photo belongs to.
     */
    public function accomplishment(): BelongsTo
    {
        return $this->belongsTo(TicketAccomplishment::class, 'accomplishment_id');
    }

    /**
     * Resolves the full URL for the photo across local and cloud disks.
     */
    public function getUrlAttribute(): string
    {
        if (str_starts_with($this->file_path, 'http://') || str_starts_with($this->file_path, 'https://')) {
            return $this->file_path;
        }

        $disk = config('filesystems.default', 'public');

        return \Illuminate\Support\Facades\Storage::disk($disk)->url($this->file_path);
    }
}
