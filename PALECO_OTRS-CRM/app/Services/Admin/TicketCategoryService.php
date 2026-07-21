<?php

namespace App\Services\Admin;

use App\Models\TicketCategory;

/*
 * Encapsulates the business logic for managing Ticket Categories.
 * Provides abstracted querying and safe restoration/deletion mechanics.
 */
class TicketCategoryService
{
    // --- VIEW DATA AGGREGATION ---

    /*
     * Retrieves a paginated list of ticket categories filtered by search or active/archived state.
     */
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

    // --- MUTATING & STATE METHODS ---

    /*
     * Updates an existing ticket category.
     * Returns a boolean indicating whether the record was actually modified.
     */
    public function updateCategory(TicketCategory $category, array $data): bool
    {
        $category->fill($data);
        
        if ($category->isClean()) {
            return false;
        }

        $category->save();
        return true;
    }

    /*
     * Attempts to restore a soft-deleted ticket category.
     * Prevents restoration if an active category has since claimed the same name.
     */
    public function restoreCategory(int $id): array
    {
        $category = TicketCategory::onlyTrashed()->findOrFail($id);

        if (TicketCategory::where('category_name', $category->category_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore category. An active category with the same name already exists.'];
        }

        $category->restore();
        return ['success' => true, 'message' => 'Category restored successfully.'];
    }

    // --- DESTRUCTIVE METHODS ---

    /*
     * Proactively checks if the category is tied to any existing service tickets.
     * Blocks permanent deletion to preserve the historical integrity of logged complaints.
     */
    public function permanentlyDeleteCategory(int $id): array
    {
        $category = TicketCategory::onlyTrashed()->findOrFail($id);

        // Check if any tickets are using this category using the defined relationship
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