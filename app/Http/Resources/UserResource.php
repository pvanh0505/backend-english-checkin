<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'is_active' => $this->is_active,
            'checkins_count' => $this->whenCounted('checkins'),
            'last_checkin_date' => $this->when(
                array_key_exists('checkins_max_checkin_date', $this->resource->getAttributes()),
                fn () => $this->checkins_max_checkin_date ? substr((string) $this->checkins_max_checkin_date, 0, 10) : null
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
