<?php

namespace App\Actions\Categories;

use App\Models\TicketCategory;

/**
 * Single-Action: Restores an archived ticket category back to active status.
 */
class RestoreTicketCategory
{
    /**
     * @return array{success: bool, message: string}
     */
    public function handle(int $id): array
    {
        return $this->execute($id);
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function execute(int $id): array
    {
        $category = TicketCategory::withTrashed()->find($id);

        if (! $category) {
            return ['success' => false, 'message' => 'Category no longer exists. It may have been permanently deleted.'];
        }

        if (! $category->trashed()) {
            return ['success' => false, 'message' => 'Category is already active.'];
        }

        if (TicketCategory::where('category_name', $category->category_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore category. An active category with the same name already exists.'];
        }

        $category->restore();

        return ['success' => true, 'message' => 'Category restored successfully.'];
    }
}
