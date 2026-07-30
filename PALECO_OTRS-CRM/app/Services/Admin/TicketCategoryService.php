<?php

namespace App\Services\Admin;

use App\Models\TicketCategory;

/*
 * Encapsulates the business logic for managing Ticket Categories.
 * Provides abstracted querying and safe restoration/deletion mechanics.
 */
class TicketCategoryService
{
    public function getDashboardCategories(array $filters)
    {
        $query = TicketCategory::query()
            ->search($filters['search'] ?? null)
            ->sort($filters['sort'] ?? null);

        if (($filters['filter'] ?? null) === 'archived') {
            $query->onlyTrashed();
        }

        return $query->paginate(10)->withQueryString();
    }

    public function updateCategory(TicketCategory $category, array $data): array
    {
        if ((string) $category->updated_at !== $data['original_updated_at']) {
            return ['success' => false, 'message' => 'Conflict: This category was modified by another user while you were editing.'];
        }
        unset($data['original_updated_at']);

        $category->fill($data);
        
        if ($category->isClean()) return ['success' => true, 'changed' => false];

        $category->save();
        return ['success' => true, 'changed' => true];
    }

    /*
     * Safely archives a category only if it has no active tickets attached.
     */
    public function archiveCategory(TicketCategory $category): array
    {
        if ($category->ticket()->exists()) {
            return [
                'success' => false, 
                'message' => 'Cannot archive this category because it is currently assigned to existing service tickets.'
            ];
        }

        $category->delete();
        return ['success' => true, 'message' => 'Category archived successfully.'];
    }

    public function restoreCategory(int $id): array
    {
        $category = TicketCategory::onlyTrashed()->findOrFail($id);

        if (TicketCategory::where('category_name', $category->category_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore category. An active category with the same name already exists.'];
        }

        $category->restore();
        return ['success' => true, 'message' => 'Category restored successfully.'];
    }

    public function permanentlyDeleteCategory(int $id): array
    {
        $category = TicketCategory::onlyTrashed()->findOrFail($id);

        if ($category->ticket()->exists()) {
            return [
                'success' => false, 
                'message' => 'Cannot permanently delete this category because it is currently referenced by existing service tickets.'
            ];
        }

        $category->forceDelete();

        return [
            'success' => true, 
            'message' => 'Category permanently deleted.'
        ];
    }
}