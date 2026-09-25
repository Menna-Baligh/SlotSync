<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->resource = Resource::create(['name' => 'Main Room', 'capacity' => 10]);
});

test('creates a pending reservation successfully', function () {
    $response = $this->postJson('/api/reservations', [
        'resource_id' => $this->resource->id,
        'units' => 5,
        'start_time' => '2026-10-01 10:00:00',
        'end_time' => '2026-10-01 11:00:00',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', 'PENDING');

    $this->assertDatabaseHas('reservations', [
        'resource_id' => $this->resource->id,
        'units' => 5,
        'status' => ReservationStatus::PENDING->value,
    ]);
});

test('confirms a pending reservation and logs history', function () {
    $reservation = Reservation::create([
        'resource_id' => $this->resource->id,
        'units' => 4,
        'start_time' => '2026-10-01 10:00:00',
        'end_time' => '2026-10-01 11:00:00',
        'status' => ReservationStatus::PENDING,
        'expires_at' => now()->addMinutes(2),
    ]);

    $response = $this->postJson("/api/reservations/{$reservation->id}/confirm");

    $response->assertOk()
        ->assertJsonPath('data.status', 'CONFIRMED');

    $this->assertDatabaseHas('reservation_histories', [
        'reservation_id' => $reservation->id,
        'action' => 'CONFIRMED',
    ]);
});

test('cancels an active reservation and frees capacity logically', function () {
    $reservation = Reservation::create([
        'resource_id' => $this->resource->id,
        'units' => 10,
        'start_time' => '2026-10-01 10:00:00',
        'end_time' => '2026-10-01 11:00:00',
        'status' => ReservationStatus::CONFIRMED,
    ]);

    $this->postJson("/api/reservations/{$reservation->id}/cancel")->assertOk();

    $this->assertDatabaseHas('reservations', [
        'id' => $reservation->id,
        'status' => ReservationStatus::CANCELLED->value,
    ]);

    // Verify freed capacity allows new 10 unit booking
    $this->postJson('/api/reservations', [
        'resource_id' => $this->resource->id,
        'units' => 10,
        'start_time' => '2026-10-01 10:00:00',
        'end_time' => '2026-10-01 11:00:00',
    ])->assertCreated();
});

test('updates reservation units and time window excluding self capacity', function () {
    $reservation = Reservation::create([
        'resource_id' => $this->resource->id,
        'units' => 8,
        'start_time' => '2026-10-01 10:00:00',
        'end_time' => '2026-10-01 11:00:00',
        'status' => ReservationStatus::CONFIRMED,
    ]);

    $response = $this->putJson("/api/reservations/{$reservation->id}", [
        'units' => 10,
        'start_time' => '2026-10-01 10:00:00',
        'end_time' => '2026-10-01 11:00:00',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.units', 10);
});
