<?php

namespace App\Services\Categories;

use App\Models\TicketCategory;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Service encapsulating query aggregators and details for Ticket Categories.
 */
class TicketCategoryService
{
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
}
