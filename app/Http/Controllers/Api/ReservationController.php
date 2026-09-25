<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Http\Resources\ReservationHistoryResource;
use App\Http\Resources\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

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

    public function cancel(int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->cancelReservation($id);

            return $this->successResponse(
                data: new ReservationResource($reservation),
                message: 'Reservation cancelled successfully.',
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

    public function update(UpdateReservationRequest $request, int $id): JsonResponse
    {
        try {
            $reservation = $this->reservationService->updateReservation($id, $request->validated());

            return $this->successResponse(
                data: new ReservationResource($reservation),
                message: 'Reservation updated successfully.',
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

    public function history(int $id): JsonResponse
    {
        $reservation = Reservation::with('histories')->find($id);
        if (! $reservation) {
            return $this->errorResponse('Reservation not found.', Response::HTTP_NOT_FOUND);
        }

        return $this->successResponse(
            data: ReservationHistoryResource::collection($reservation->histories),
            message: 'Reservation history retrieved successfully.'
        );
    }
}
