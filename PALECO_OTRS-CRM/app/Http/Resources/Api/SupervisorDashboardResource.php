<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the supervisor dashboard KPIs and accordion ticket collections into a structured mobile payload.
 */
class SupervisorDashboardResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $mappedAccordions = collect($this['accordions'])->map(function ($section) {
            return [
                'total_count' => $section['total_count'],
                'tickets' => TicketResource::collection($section['tickets']),
            ];
        });

        return [
            'kpis' => $this['kpis'],
            'accordions' => $mappedAccordions,
        ];
    }
}
