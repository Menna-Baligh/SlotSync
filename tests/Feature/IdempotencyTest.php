<?php

use App\Models\IdempotencyKey;
use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->resource = Resource::create(['name' => 'Lab 1', 'capacity' => 10]);
});


test('returns identical response and creates only one reservation for duplicate idempotency key', function () {
    $key = 'UNIQUE-KEY-12345';
    $payload = [
        'resource_id' => $this->resource->id,
        'units'       => 3,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $res1 = $this->withHeader('Idempotency-Key', $key)->postJson('/api/reservations', $payload);
    $res1->assertCreated();
    $reservationId1 = $res1->json('data.id');

    $res2 = $this->withHeader('Idempotency-Key', $key)->postJson('/api/reservations', $payload);
    $res2->assertCreated();
    $reservationId2 = $res2->json('data.id');

    expect($reservationId1)->toBe($reservationId2);
    $this->assertDatabaseCount('reservations', 1);
});


test('rejects request when same idempotency key is reused with different payload', function () {
    $key = 'UNIQUE-KEY-12345';

    $payloadOriginal = [
        'resource_id' => $this->resource->id,
        'units'       => 3,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $payloadModified = [
        'resource_id' => $this->resource->id,
        'units'       => 5,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/reservations', $payloadOriginal)->assertCreated();

    $res2 = $this->withHeader('Idempotency-Key', $key)->postJson('/api/reservations', $payloadModified);
    $res2->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonPath('message', 'This Idempotency-Key has already been used with a different request payload.');
});


test('blocks a concurrent duplicate request while the first is still processing', function () {
    $key      = 'RACE-KEY-99999';
    $endpoint = 'POST api/reservations';

    $payload = [
        'resource_id' => $this->resource->id,
        'units'       => 3,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $requestHash = hash('sha256', json_encode($payload));

    IdempotencyKey::create([
        'key'          => $key,
        'endpoint'     => $endpoint,
        'request_hash' => $requestHash,
        'status'       => 'processing',
        'response_code' => null,
        'response_body' => null,
    ]);

    $response = $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/reservations', $payload);

    $response->assertStatus(409)
        ->assertJsonPath('success', false);

    $this->assertDatabaseCount('reservations', 0);
});


test('allows retry with same idempotency key after a server error', function () {
    $key      = 'RETRY-KEY-77777';
    $endpoint = 'POST api/reservations';

    $payload = [
        'resource_id' => $this->resource->id,
        'units'       => 3,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $requestHash = hash('sha256', json_encode($payload));


    $this->assertDatabaseMissing('idempotency_keys', ['key' => $key]);

    $response = $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/reservations', $payload);

    $response->assertCreated();
    $this->assertDatabaseCount('reservations', 1);

    $this->assertDatabaseHas('idempotency_keys', [
        'key'    => $key,
        'status' => 'completed',
    ]);
});


test('stores idempotency key for 4xx client errors (not just 2xx)', function () {
    $key = 'ERROR-KEY-44444';

    $payload = [
        'resource_id' => $this->resource->id,
        'units'       => 999,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $response = $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/reservations', $payload);

    $response->assertUnprocessable();

    $this->assertDatabaseHas('idempotency_keys', [
        'key'    => $key,
        'status' => 'completed',
    ]);

    $retry = $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/reservations', $payload);

    $retry->assertUnprocessable();
    $this->assertDatabaseCount('idempotency_keys', 1);
});


test('same idempotency key on different endpoints is treated independently', function () {
    $key = 'SHARED-KEY-11111';

    $reservation = \App\Models\Reservation::create([
        'resource_id' => $this->resource->id,
        'units'       => 3,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
        'status'      => \App\Enums\ReservationStatus::PENDING,
        'expires_at'  => now()->addMinutes(5),
    ]);

    $this->withHeader('Idempotency-Key', $key)
        ->postJson('/api/reservations', [
            'resource_id' => $this->resource->id,
            'units'       => 2,
            'start_time'  => '2026-10-02 10:00:00',
            'end_time'    => '2026-10-02 11:00:00',
        ])
        ->assertCreated();

    $this->withHeader('Idempotency-Key', $key)
        ->postJson("/api/reservations/{$reservation->id}/confirm")
        ->assertOk();

    $this->assertDatabaseCount('idempotency_keys', 2);
});
