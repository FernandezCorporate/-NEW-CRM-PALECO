<?php

namespace App\Actions\Categories;

use App\Enums\TicketStatus;
use App\Models\TicketCategory;
use Illuminate\Support\Facades\DB;

/**
 * Single-Action: Soft-deletes (archives) a ticket category if no active service tickets are linked.
 */
class ArchiveTicketCategory
{
    /**
     * @return array{success: bool, message: string}
     */
    public function handle(TicketCategory $category): array
    {
        return $this->execute($category);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(TicketCategory $category): array
    {
        return DB::transaction(function () use ($category) {
            $lockedCategory = TicketCategory::where('id', $category->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedCategory || $lockedCategory->trashed()) {
                return [
                    'success' => false,
                    'message' => 'Conflict: This category has already been archived by an administrator.',
                ];
            }

            // Only a category whose tickets are all finished can be retired
            $hasUnfinishedTickets = $lockedCategory->ticket()
                ->whereNotIn('status', [
                    TicketStatus::CLOSED,
                    TicketStatus::CANCELLED,
                ])
                ->exists();

            if ($hasUnfinishedTickets) {
                return [
                    'success' => false,
                    'message' => 'Cannot archive this category while any of its tickets is not yet closed or cancelled.',
                ];
            }

            $lockedCategory->delete();

            return ['success' => true, 'message' => 'Category archived successfully.'];
        });
    }
}

