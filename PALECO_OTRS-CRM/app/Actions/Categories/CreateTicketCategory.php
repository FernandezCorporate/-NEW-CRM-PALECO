<?php

namespace App\Actions\Categories;

use App\Models\TicketCategory;

/**
 * Single-Action: Stores a new ticket category in the database.
 */
class CreateTicketCategory
{
    public function handle(array $data): TicketCategory
    {
        return $this->execute($data);
    }

    public function execute(array $data): TicketCategory
    {
        unset($data['original_updated_at']);

        return TicketCategory::create($data);
    }
}

