<?php

namespace App\Console\Commands;

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\ReservationHistory;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ExpireReservationsCommand extends Command
{
    protected $signature = 'reservations:expire';

    protected $description = 'Find overdue pending reservations and mark them as expired.';

    public function handle(): int
    {
        $now = Carbon::now();

        $expiredIds = Reservation::query()
            ->where('status', ReservationStatus::PENDING->value)
            ->where('expires_at', '<=', $now)
            ->pluck('id');

        if ($expiredIds->isEmpty()) {
            $this->info('No overdue pending reservations found.');

            return Command::SUCCESS;
        }

        $count = 0;

        foreach ($expiredIds as $id) {
            try {
                DB::transaction(function () use ($id, &$count) {
                    $reservation = Reservation::query()
                        ->where('id', $id)
                        ->lockForUpdate()
                        ->first();

                    if ($reservation && $reservation->status === ReservationStatus::PENDING) {

                        $oldPayload = [
                            'status' => $reservation->status->value,
                            'expires_at' => $reservation->expires_at ? $reservation->expires_at->toIso8601String() : null,
                        ];

                        $reservation->update([
                            'status' => ReservationStatus::EXPIRED,
                            'expires_at' => null,
                        ]);

                        ReservationHistory::create([
                            'reservation_id' => $reservation->id,
                            'action' => 'EXPIRED',
                            'old_payload' => $oldPayload,
                            'new_payload' => ['status' => ReservationStatus::EXPIRED->value],
                        ]);

                        $count++;
                    }
                });
            } catch (Throwable $e) {
                $this->error("Failed to expire reservation #{$id}: ".$e->getMessage());
            }
        }

        $this->info("Successfully expired {$count} pending reservation(s).");

        return Command::SUCCESS;
    }
}
