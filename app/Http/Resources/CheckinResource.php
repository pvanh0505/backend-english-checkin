<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Checkin */
class CheckinResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'checkin_date' => substr((string) $this->checkin_date, 0, 10),
            'learned_date' => substr((string) $this->learned_date, 0, 10),
            'note' => $this->note,
            'sentence_count' => $this->sentence_count,
            'sentences' => $this->whenLoaded('sentences', fn () => $this->sentences->map(fn ($s) => [
                'id' => $s->id,
                'content' => $s->content,
                'meaning' => $s->meaning,
                'note' => $s->note,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
