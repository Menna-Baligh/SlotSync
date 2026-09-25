<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReservationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'resource_id' => $this->resource_id,
            'units'       => $this->units,
            'status'      => $this->status->value,
            'start_time'  => $this->start_time->format('H:i:s'),
            'end_time'    => $this->end_time->format('H:i:s'),
            'expires_at'  => $this->expires_at ? $this->expires_at->format('H:i:s') : null,
        ];
    }
}
