<?php

namespace App\Services;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use App\Models\Resource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ReservationService
{
    public function __construct(
        protected ReservationAvailabilityService $availabilityService
    ) {}


    public function createReservation(array $data): Reservation
    {
        return DB::transaction(function () use ($data) {

            $resource = Resource::query()
                ->where('id', $data['resource_id'])
                ->lockForUpdate()
                ->firstOrFail();

            $startTime = Carbon::parse($data['start_time']);
            $endTime = Carbon::parse($data['end_time']);
            $requestedUnits = (int) $data['units'];

            $canBook = $this->availabilityService->canBook(
                $resource,
                $startTime,
                $endTime,
                $requestedUnits
            );

            if (! $canBook) {
                throw new RuntimeException('Insufficient capacity available for the requested time interval.');
            }

            $expiresAt = Carbon::now()->addMinutes(2);

            $reservation = Reservation::create([
                'resource_id' => $resource->id,
                'units'       => $requestedUnits,
                'start_time'  => $startTime,
                'end_time'    => $endTime,
                'status'      => ReservationStatus::PENDING,
                'expires_at'  => $expiresAt,
            ]);

            ReservationHistory::create([
                'reservation_id' => $reservation->id,
                'action'         => 'CREATED',
                'old_payload'    => null,
                'new_payload'    => [
                    'units'      => $reservation->units,
                    'start_time' => $reservation->start_time,
                    'end_time'   => $reservation->end_time,
                    'status'     => $reservation->status->value,
                    'expires_at' => $reservation->expires_at,
                ],
            ]);

            return $reservation;
        });
    }

    public function confirmReservation(int $reservationId): Reservation
    {
        return DB::transaction(function () use ($reservationId) {

            $reservation = Reservation::query()
                ->where('id', $reservationId)
                ->lockForUpdate()
                ->first();

            if (! $reservation) {
                throw new RuntimeException('Reservation not found.', 404);
            }

            if ($reservation->status !== ReservationStatus::PENDING) {
                throw new RuntimeException(
                    "Cannot confirm reservation with status '{$reservation->status->value}'.",
                    422
                );
            }

            if ($reservation->expires_at && Carbon::now()->greaterThan($reservation->expires_at)) {

                $oldPayload = [
                    'status'     => $reservation->status->value,
                    'expires_at' => $reservation->expires_at,
                ];

                $reservation->update([
                    'status'     => ReservationStatus::EXPIRED,
                    'expires_at' => null,
                ]);

                ReservationHistory::create([
                    'reservation_id' => $reservation->id,
                    'action'         => 'EXPIRED',
                    'old_payload'    => $oldPayload,
                    'new_payload'    => ['status' => ReservationStatus::EXPIRED->value],
                ]);

                throw new RuntimeException('Reservation has expired and cannot be confirmed.', 422);
            }

            $oldPayload = [
                'status'     => $reservation->status->value,
                'expires_at' => $reservation->expires_at ? $reservation->expires_at : null,
            ];

            $reservation->update([
                'status'     => ReservationStatus::CONFIRMED,
                'expires_at' => null,
            ]);

            $newPayload = [
                'status'     => $reservation->status->value,
                'expires_at' => null,
            ];

            ReservationHistory::create([
                'reservation_id' => $reservation->id,
                'action'         => 'CONFIRMED',
                'old_payload'    => $oldPayload,
                'new_payload'    => $newPayload,
            ]);

            return $reservation;
        });
    }
}
