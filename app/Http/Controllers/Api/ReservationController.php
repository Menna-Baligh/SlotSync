<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\ReservationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use RuntimeException;

class ReservationController extends Controller
{
    use ApiResponse;
    public function __construct(
        protected ReservationService $reservationService
    ) {}

    public function store(StoreReservationRequest $request): JsonResponse
    {
        try {
            $reservation = $this->reservationService->createReservation($request->validated());

            return $this->successResponse(
                data: new ReservationResource($reservation),
                message: 'Pending reservation created successfully.',
                statusCode: Response::HTTP_CREATED
            );

        } catch (RuntimeException $e) {
            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }
    }
    public function confirm(int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->confirmReservation($id);

            return $this->successResponse(
                data: new ReservationResource($reservation),
                message: 'Reservation confirmed successfully.',
                statusCode: Response::HTTP_OK
            );

        } catch (RuntimeException $e) {
            $statusCode = $e->getCode() >= 400 && $e->getCode() < 600
                ? $e->getCode()
                : Response::HTTP_UNPROCESSABLE_ENTITY;

            return $this->errorResponse(
                message: $e->getMessage(),
                statusCode: $statusCode
            );
        }
    }
}
