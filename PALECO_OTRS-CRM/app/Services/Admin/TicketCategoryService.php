<?php

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;
use App\Models\TicketCategory;

class TicketCategoryService
{
    public function getDashboardCategories(array $filters)
    {
        $query = TicketCategory::query()
            ->withCount('ticket') 
            ->search($filters['search'] ?? null)
            ->sort($filters['sort'] ?? null);

        if (($filters['filter'] ?? null) === 'archived') {
            $query->onlyTrashed();
        }

        return $query->paginate(10)->withQueryString();
    }

    public function getCategoryDetails(TicketCategory $category): array
    {
        $assignedTickets = $category->ticket()
            ->latest('reported_at')
            ->paginate(5, ['*'], 'page_tickets')
            ->withQueryString();

        return compact('assignedTickets');
    }

    public function updateCategory(TicketCategory $category, array $data): array
    {
        $originalUpdatedAt = $data['original_updated_at'];
        unset($data['original_updated_at']);

        return DB::transaction(function () use ($category, $data, $originalUpdatedAt) {
            $lockedCategory = TicketCategory::where('id', $category->id)->lockForUpdate()->first();

            if ((string) $lockedCategory->updated_at !== $originalUpdatedAt) {
                return ['success' => false, 'message' => 'Conflict: This category was modified by another user while you were editing.'];
            }

            $lockedCategory->fill($data);
            
            if ($lockedCategory->isClean()) return ['success' => true, 'changed' => false];

            $lockedCategory->save(); 
            return ['success' => true, 'changed' => true];
        });
    }

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
        $category = TicketCategory::withTrashed()->find($id);

        if (!$category) {
            return ['success' => false, 'message' => 'Category no longer exists. It may have been permanently deleted.'];
        }

        if (!$category->trashed()) {
            return ['success' => false, 'message' => 'Category is already active.'];
        }

        if (TicketCategory::where('category_name', $category->category_name)->exists()) {
            return ['success' => false, 'message' => 'Cannot restore category. An active category with the same name already exists.'];
        }

        $category->restore();
        return ['success' => true, 'message' => 'Category restored successfully.'];
    }

    public function permanentlyDeleteCategory(int $id): array
    {
        $category = TicketCategory::withTrashed()->find($id);

        if (!$category) {
            return ['success' => true, 'message' => 'Category has already been permanently deleted.'];
        }

        if (!$category->trashed()) {
            return ['success' => false, 'message' => 'Cannot permanently delete this category because it was recently restored.'];
        }

        if ($category->ticket()->exists()) {
            return ['success' => false, 'message' => 'Cannot permanently delete this category because it is currently referenced by existing service tickets.'];
        }

        $category->forceDelete();

        return ['success' => true, 'message' => 'Category permanently deleted.'];
    }
}