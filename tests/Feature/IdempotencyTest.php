<?php

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
