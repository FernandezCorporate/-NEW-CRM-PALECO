<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'team_name'     => $this->team_name,
            'shift_start'   => $this->shift_start?->format('h:i A'),
            'shift_end'     => $this->shift_end?->format('h:i A'),
            'members_count' => $this->members_count ?? 0,
        ];
    }
}