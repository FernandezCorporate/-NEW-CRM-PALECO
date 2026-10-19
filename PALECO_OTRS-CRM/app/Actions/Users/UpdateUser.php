<?php

namespace App\Actions\Users;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Commits updates to an existing user profile with optimistic concurrency control.
 */
class UpdateUser
{
    /**
     * @return array{success: bool, changed: bool, message?: string}
     */
    public function handle(User $user, array $data): array
    {
        return $this->execute($user, $data);
    }

    /**
     * @return array{success: bool, changed: bool, message?: string}
     */
    public function execute(User $user, array $data): array
    {
        $originalUpdatedAt = $data['original_updated_at'] ?? null;
        unset($data['original_updated_at']);

        if ($user->role && $user->role->slug_identifier === 'field_personnel') {
            $data['department_id'] = null;
        }

        return DB::transaction(function () use ($user, $data, $originalUpdatedAt) {
            $lockedUser = User::query()->where('id', $user->id)->lockForUpdate()->first();

            if ($originalUpdatedAt && ! $lockedUser->updated_at->eq(Carbon::parse($originalUpdatedAt))) {
                return ['success' => false, 'changed' => false, 'message' => 'Conflict: This user profile was modified by another admin while you were editing.'];
            }

            $lockedUser->fill($data);

            if ($lockedUser->isClean()) {
                return ['success' => true, 'changed' => false];
            }

            $lockedUser->save();

            return ['success' => true, 'changed' => true];
        });
    }
}

