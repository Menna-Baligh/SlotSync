<?php

use App\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\Resource;
use App\Services\ReservationAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class); 

beforeEach(function () {
    $this->service = app(ReservationAvailabilityService::class);
    $this->resource = Resource::create([
        'name'     => 'Hall A',
        'capacity' => 10,
    ]);
});

test('returns full capacity when no reservations exist', function () {
    $canBook = $this->service->canBook(
        $this->resource,
        Carbon::parse('2026-10-01 10:00:00'),
        Carbon::parse('2026-10-01 12:00:00'),
        10
    );

    expect($canBook)->toBeTrue();
});

test('allows booking up to remaining capacity with one active reservation', function () {
    Reservation::create([
        'resource_id' => $this->resource->id,
        'units'       => 6,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 12:00:00',
        'status'      => ReservationStatus::CONFIRMED,
    ]);

    expect($this->service->canBook($this->resource, Carbon::parse('2026-10-01 10:00:00'), Carbon::parse('2026-10-01 12:00:00'), 4))->toBeTrue()
        ->and($this->service->canBook($this->resource, Carbon::parse('2026-10-01 10:00:00'), Carbon::parse('2026-10-01 12:00:00'), 5))->toBeFalse();
});

test('correctly handles adjacent reservations without false overlap', function () {
    Reservation::create([
        'resource_id' => $this->resource->id,
        'units'       => 10,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
        'status'      => ReservationStatus::CONFIRMED,
    ]);

    $canBook = $this->service->canBook(
        $this->resource,
        Carbon::parse('2026-10-01 11:00:00'),
        Carbon::parse('2026-10-01 12:00:00'),
        10
    );

    expect($canBook)->toBeTrue();
});

test('calculates peak utilization for multiple partially overlapping reservations', function () {
    Reservation::create([
        'resource_id' => $this->resource->id,
        'units'       => 6,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
        'status'      => ReservationStatus::CONFIRMED,
    ]);

    Reservation::create([
        'resource_id' => $this->resource->id,
        'units'       => 4,
        'start_time'  => '2026-10-01 10:30:00',
        'end_time'    => '2026-10-01 12:00:00',
        'status'      => ReservationStatus::CONFIRMED,
    ]);

    $canBook = $this->service->canBook(
        $this->resource,
        Carbon::parse('2026-10-01 10:45:00'),
        Carbon::parse('2026-10-01 11:15:00'),
        1
    );

    expect($canBook)->toBeFalse();
});
