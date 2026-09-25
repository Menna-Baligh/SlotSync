<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('runtime guard rejects confirmation of overdue pending reservation', function () {
    $resource = Resource::create(['name' => 'Hall X', 'capacity' => 10]);

    $reservation = Reservation::create([
        'resource_id' => $resource->id,
        'units'       => 5,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
        'status'      => ReservationStatus::PENDING,
        'expires_at'  => Carbon::now()->subMinutes(5),
    ]);

    $response = $this->postJson("/api/reservations/{$reservation->id}/confirm");

    $response->assertUnprocessable()
        ->assertJsonPath('message', 'Reservation has expired and cannot be confirmed.');
});

test('artisan command cleans up overdue pending reservations', function () {
    $resource = Resource::create(['name' => 'Hall Y', 'capacity' => 10]);

    $reservation = Reservation::create([
        'resource_id' => $resource->id,
        'units'       => 2,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
        'status'      => ReservationStatus::PENDING,
        'expires_at'  => Carbon::now()->subMinute(),
    ]);

    $this->artisan('reservations:expire')
        ->expectsOutput('Successfully expired 1 pending reservation(s).')
        ->assertExitCode(0);

    $this->assertDatabaseHas('reservations', [
        'id'     => $reservation->id,
        'status' => ReservationStatus::EXPIRED->value,
    ]);
});
