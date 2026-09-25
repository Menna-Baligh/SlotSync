<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ResourceService
{
    public function __construct(
        protected ReservationAvailabilityService $availabilityService
    ) {}

    public function updateCapacity(int $resourceId, int $newCapacity): Resource
    {
        return DB::transaction(function () use ($resourceId, $newCapacity) {

            $resource = Resource::query()
                ->where('id', $resourceId)
                ->lockForUpdate()
                ->first();

            if (! $resource) {
                throw new RuntimeException('Resource not found.', 404);
            }

            $now = Carbon::now();

            $activeReservations = Reservation::query()
                ->where('resource_id', $resource->id)
                ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
                ->where(function ($query) use ($now) {
                    $query->where('status', '!=', ReservationStatus::PENDING->value)
                        ->orWhere('expires_at', '>', $now);
                })
                ->where('end_time', '>', $now)
                ->get();

            if ($activeReservations->isNotEmpty()) {
                $minStart = $activeReservations->min('start_time');
                $maxEnd = $activeReservations->max('end_time');

                $maxReservedUnits = $this->availabilityService->calculateMaxConcurrentUnits(
                    $activeReservations,
                    $minStart,
                    $maxEnd
                );

                if ($newCapacity < $maxReservedUnits) {
                    throw new RuntimeException(
                        "Cannot reduce capacity to {$newCapacity}. Active bookings require at least {$maxReservedUnits} units.",
                        422
                    );
                }
            }

            $resource->update([
                'capacity' => $newCapacity,
            ]);

            return $resource;
        });
    }
}
