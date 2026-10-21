<?php

namespace App\Actions\Categories;

use App\Models\TicketCategory;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Updates category information using optimistic locking protection.
 */
class UpdateTicketCategory
{
    /**
     * @return array{success: bool, changed?: bool, message?: string}
     */
    public function handle(TicketCategory $category, array $data): array
    {
        return $this->execute($category, $data);
    }

    /**
     * @return array{success: bool, changed?: bool, message?: string}
     */
    public function execute(TicketCategory $category, array $data): array
    {
        $originalUpdatedAt = $data['original_updated_at'];
        unset($data['original_updated_at']);

        return DB::transaction(function () use ($category, $data, $originalUpdatedAt) {
            $lockedCategory = TicketCategory::where('id', $category->id)->lockForUpdate()->first();

            if (! $lockedCategory->updated_at->eq(Carbon::parse($originalUpdatedAt))) {
                return ['success' => false, 'message' => 'Conflict: This category was modified by another user while you were editing.'];
            }

            $lockedCategory->fill($data);

            if ($lockedCategory->isClean()) {
                return ['success' => true, 'changed' => false];
            }

            $lockedCategory->save();

            return ['success' => true, 'changed' => true];
        });
    }
}
