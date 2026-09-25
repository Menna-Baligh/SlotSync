<?php

use App\Models\Resource;
use App\Services\ReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
uses(TestCase::class, RefreshDatabase::class);

uses(RefreshDatabase::class);

test('prevents overbooking when two simultaneous requests race for remaining capacity', function () {
    $resource = Resource::create(['name' => 'Concurrency Room', 'capacity' => 10]);

    $payloadA = [
        'resource_id' => $resource->id,
        'units'       => 6,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $payloadB = [
        'resource_id' => $resource->id,
        'units'       => 6,
        'start_time'  => '2026-10-01 10:00:00',
        'end_time'    => '2026-10-01 11:00:00',
    ];

    $service = app(ReservationService::class);

    $successCount = 0;
    $failureCount = 0;

    foreach ([$payloadA, $payloadB] as $payload) {
        try {
            $service->createReservation($payload);
            $successCount++;
        } catch (\Exception $e) {
            $failureCount++;
        }
    }

    expect($successCount)->toBe(1)
        ->and($failureCount)->toBe(1);

    $totalUnitsInDb = (int) DB::table('reservations')->where('resource_id', $resource->id)->sum('units');
    expect($totalUnitsInDb)->toBe(6);
});
