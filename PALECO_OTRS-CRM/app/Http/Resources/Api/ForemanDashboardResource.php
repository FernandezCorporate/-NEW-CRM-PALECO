<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Api\TicketResource;

class ForemanDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $mappedAccordions = collect($this['accordions'])->map(function ($section) {
            return [
                'total_count' => $section['total_count'],
                'tickets'     => TicketResource::collection($section['tickets']),
            ];
        });

        return [
            'kpis'       => $this['kpis'],
            'accordions' => $mappedAccordions,
        ];
    }
}