<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A phone registered to receive push notifications for one login of a mobile account.
 */
#[Fillable(['user_id', 'personal_access_token_id', 'fcm_token', 'platform', 'last_seen_at'])]
class PushDevice extends Model
{
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }

    // --- SCOPES ---

    /**
     * Phones whose login is still valid, using the same rule as Sanctum's guard:
     * younger than the configured expiration (30 days) and not past its own expires_at.
     * A phone whose login expired keeps its row until the user logs in again, but gets nothing.
     */
    public function scopeWithLiveLogin(Builder $query): Builder
    {
        return $query->whereHas('accessToken', function (Builder $token) {
            $token->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));

            if ($minutes = config('sanctum.expiration')) {
                $token->where('created_at', '>', now()->subMinutes($minutes));
            }
        });
    }

    // --- RELATIONSHIPS ---

    /**
     * The account the phone receives notifications for.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The login the phone registered under; deleting it removes this phone.
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'personal_access_token_id');
    }
}
