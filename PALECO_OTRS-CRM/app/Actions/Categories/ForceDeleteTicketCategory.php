<?php

namespace App\Actions\Categories;

use App\Models\TicketCategory;

/**
 * Single-Action: Permanently deletes an archived ticket category from the database.
 */
class ForceDeleteTicketCategory
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
            return ['success' => true, 'message' => 'Category has already been permanently deleted.'];
        }

        if (! $category->trashed()) {
            return ['success' => false, 'message' => 'Cannot permanently delete an active category. Please archive it first.'];
        }

        if ($category->ticket()->exists()) {
            return [
                'success' => false,
                'message' => "Cannot permanently delete category '{$category->category_name}' because it contains historical service ticket records.",
            ];
        }

        $category->forceDelete();

        return ['success' => true, 'message' => 'Category permanently deleted.'];
    }
}

