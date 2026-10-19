<?php

namespace App\Services\Categories;

use App\Actions\Categories\ArchiveTicketCategory;
use App\Actions\Categories\CreateTicketCategory;
use App\Actions\Categories\ForceDeleteTicketCategory;
use App\Actions\Categories\RestoreTicketCategory;
use App\Actions\Categories\UpdateTicketCategory;
use App\Models\TicketCategory;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service encapsulating query aggregators and details for Ticket Categories.
 * Delegates mutations to dedicated Action classes:
 * - CreateTicketCategory
 * - UpdateTicketCategory
 * - ArchiveTicketCategory
 * - RestoreTicketCategory
 * - ForceDeleteTicketCategory
 */
class TicketCategoryService
{
    public function __construct(
        protected CreateTicketCategory $createCategoryAction,
        protected UpdateTicketCategory $updateCategoryAction,
        protected ArchiveTicketCategory $archiveCategoryAction,
        protected RestoreTicketCategory $restoreCategoryAction,
        protected ForceDeleteTicketCategory $forceDeleteCategoryAction
    ) {}

    // --- QUERY METHODS ---

    /**
     * Retrieves paginated ticket categories with complaint counts and filter criteria.
     */
    public function getDashboardCategories(array $filters): LengthAwarePaginator
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

    /**
     * Retrieves category profile details and linked complaint tickets.
     */
    public function getCategoryDetails(TicketCategory $category): array
    {
        $assignedTickets = $category->ticket()
            ->latest('reported_at')
            ->paginate(5, ['*'], 'page_tickets')
            ->withQueryString();

        return compact('assignedTickets');
    }

    // --- MUTATING METHODS ---

    /**
     * Stores a new ticket category in the database.
     */
    public function createCategory(array $data): TicketCategory
    {
        return $this->createCategoryAction->execute($data);
    }

    /**
     * Updates category information using optimistic locking protection.
     */
    public function updateCategory(TicketCategory $category, array $data): array
    {
        return $this->updateCategoryAction->execute($category, $data);
    }

    /**
     * Soft-deletes (archives) a ticket category if no active service tickets are linked.
     */
    public function archiveCategory(TicketCategory $category): array
    {
        return $this->archiveCategoryAction->execute($category);
    }

    /**
     * Restores an archived ticket category back to active status.
     */
    public function restoreCategory(int $id): array
    {
        return $this->restoreCategoryAction->execute($id);
    }

    /**
     * Permanently deletes an archived ticket category from the database.
     */
    public function permanentlyDeleteCategory(int $id): array
    {
        return $this->forceDeleteCategoryAction->execute($id);
    }
}
