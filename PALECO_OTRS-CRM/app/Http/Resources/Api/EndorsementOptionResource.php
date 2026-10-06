<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/*
 * Transforms the Department model into a lightweight option payload for mobile endorsement dropdowns.
 */
class EndorsementOptionResource extends JsonResource
{
    /*
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'department_name' => $this->dept_name,
            'department_description' => $this->dept_desc,
            'is_current' => $this->is_current ?? false,
        ];
    }
}
