<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('allows admin to increase or safely decrease resource capacity', function () {
    $resource = Resource::create(['name' => 'Auditorium', 'capacity' => 10]);

    $this->patchJson("/api/resources/{$resource->id}/capacity", ['capacity' => 20])
        ->assertOk()
        ->assertJsonPath('data.capacity', 20);

    $this->assertDatabaseHas('resources', ['id' => $resource->id, 'capacity' => 20]);
});

test('rejects capacity reduction below current active booked peak', function () {
    $resource = Resource::create(['name' => 'Auditorium', 'capacity' => 10]);

    Reservation::create([
        'resource_id' => $resource->id,
        'units'       => 7,
        'start_time'  => now()->addHour(),
        'end_time'    => now()->addHours(2),
        'status'      => ReservationStatus::CONFIRMED,
    ]);

    $response = $this->patchJson("/api/resources/{$resource->id}/capacity", ['capacity' => 5]);

    $response->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'Cannot reduce capacity to 5. Active bookings require at least 7 units.');
});
