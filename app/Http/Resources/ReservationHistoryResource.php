<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

class ReservationHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reservation_id' => $this->reservation_id,
            'action' => $this->action,
            'old_payload' => $this->formatPayloadDates($this->old_payload),
            'new_payload' => $this->formatPayloadDates($this->new_payload),
            'created_at' => $this->created_at ? $this->created_at->format('Y-m-d H:i:s') : null,
        ];
    }

    protected function formatPayloadDates(?array $payload): ?array
    {
        if (! $payload) {
            return null;
        }

        $dateKeys = ['start_time', 'end_time', 'expires_at'];

        foreach ($dateKeys as $key) {
            if (isset($payload[$key]) && ! empty($payload[$key])) {
                try {
                    $payload[$key] = Carbon::parse($payload[$key])
                        ->setTimezone('Africa/Cairo')
                        ->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                }
            }
        }

        return $payload;
    }
}
