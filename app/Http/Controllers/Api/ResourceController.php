<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateResourceCapacityRequest;
use App\Services\ResourceService;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use RuntimeException;

class ResourceController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected ResourceService $resourceService
    ) {}

    public function updateCapacity(UpdateResourceCapacityRequest $request, int $id): JsonResponse
    {
        try {
            $resource = $this->resourceService->updateCapacity($id, (int) $request->validated('capacity'));

            return $this->successResponse(
                data: [
                    'id'       => $resource->id,
                    'name'     => $resource->name,
                    'capacity' => $resource->capacity,
                ],
                message: 'Resource capacity updated successfully.',
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
