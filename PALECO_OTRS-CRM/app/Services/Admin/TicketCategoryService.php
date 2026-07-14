<?php

namespace App\Services\Admin;

use App\Models\TicketCategory;

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

    public function updateCategory(TicketCategory $category, array $data): bool
    {
        $category->fill($data);
        
        if ($category->isClean()) {
            return false;
        }

        $category->save();
        return true;
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
}