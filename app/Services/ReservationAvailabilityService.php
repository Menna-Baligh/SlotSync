<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

class ReservationAvailabilityService
{
    public function calculateAvailability(Resource $resource, CarbonInterface $startTime, CarbonInterface $endTime, ?int $ignoreReservationId = null): array
    {
        $activeReservations = $this->getRelevantReservations(
            $resource->id,
            $startTime,
            $endTime,
            $ignoreReservationId
        );

        $maxReservedUnits = $this->calculateMaxConcurrentUnits(
            $activeReservations,
            $startTime,
            $endTime
        );

        $availableUnits = max(0, $resource->capacity - $maxReservedUnits);

        return [
            'resource_id' => $resource->id,
            'capacity' => $resource->capacity,
            'max_reserved_units' => $maxReservedUnits,
            'available_units' => $availableUnits,
        ];
    }

    public function canBook(Resource $resource, CarbonInterface $startTime, CarbonInterface $endTime, int $requestedUnits, ?int $ignoreReservationId = null): bool
    {
        if ($requestedUnits <= 0) {
            return false;
        }

        $availability = $this->calculateAvailability(
            $resource,
            $startTime,
            $endTime,
            $ignoreReservationId
        );

        return $availability['available_units'] >= $requestedUnits;
    }

    protected function getRelevantReservations(int $resourceId, CarbonInterface $startTime, CarbonInterface $endTime, ?int $ignoreReservationId = null): Collection
    {
        $now = Carbon::now();

        return Reservation::query()
            ->where('resource_id', $resourceId)
            ->whereIn('status', [ReservationStatus::PENDING->value, ReservationStatus::CONFIRMED->value])
            ->where(function ($query) use ($now) {
                $query->where('status', '!=', ReservationStatus::PENDING->value)
                    ->orWhere('expires_at', '>', $now);
            })
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->when($ignoreReservationId, function ($query, $id) {
                $query->where('id', '!=', $id);
            })
            ->get();
    }

    public function calculateMaxConcurrentUnits(Collection $reservations, CarbonInterface $rangeStart, CarbonInterface $rangeEnd): int
    {
        $events = [];

        foreach ($reservations as $reservation) {
            $eventStart = $reservation->start_time->greaterThan($rangeStart)
                ? $reservation->start_time
                : $rangeStart;

            $eventEnd = $reservation->end_time->lessThan($rangeEnd)
                ? $reservation->end_time
                : $rangeEnd;

            if ($eventStart->lessThan($eventEnd)) {
                $events[] = [
                    'time' => $eventStart->timestamp,
                    'type' => 1,
                    'units' => $reservation->units,
                ];

                $events[] = [
                    'time' => $eventEnd->timestamp,
                    'type' => -1,
                    'units' => -$reservation->units,
                ];
            }
        }

        usort($events, function ($a, $b) {
            if ($a['time'] === $b['time']) {
                return $a['type'] <=> $b['type'];
            }

            return $a['time'] <=> $b['time'];
        });

        $currentUnits = 0;
        $maxUnits = 0;

        foreach ($events as $event) {
            $currentUnits += $event['units'];
            if ($currentUnits > $maxUnits) {
                $maxUnits = $currentUnits;
            }
        }

        return $maxUnits;
    }
}
