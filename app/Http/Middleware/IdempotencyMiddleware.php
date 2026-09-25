<?php

namespace App\Http\Middleware;

use App\Models\IdempotencyKey;
use App\Traits\ApiResponse;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    use ApiResponse;

    public function handle(Request $request, Closure $next): mixed
    {
        if ($request->isMethodSafe()) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');

        if (! $key) {
            return $next($request);
        }

        $endpoint = $request->method() . ' ' . $request->path();
        $requestHash = hash('sha256', json_encode($request->all()));

        $existingKey = IdempotencyKey::query()
            ->where('key', $key)
            ->where('endpoint', $endpoint)
            ->first();

        if ($existingKey) {
            if ($existingKey->request_hash === $requestHash) {
                return response()->json(
                    $existingKey->response_body,
                    $existingKey->response_code
                );
            }

            return $this->errorResponse(
                message: 'This Idempotency-Key has already been used with a different request payload.',
                statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            try {
                $content = json_decode($response->getContent(), true);

                IdempotencyKey::create([
                    'key'           => $key,
                    'endpoint'      => $endpoint,
                    'request_hash'  => $requestHash,
                    'response_code' => $response->getStatusCode(),
                    'response_body' => $content ?? [],
                ]);
            } catch (QueryException $e) {
                $fallback = IdempotencyKey::query()
                    ->where('key', $key)
                    ->where('endpoint', $endpoint)
                    ->first();

                if ($fallback) {
                    return response()->json(
                        $fallback->response_body,
                        $fallback->response_code
                    );
                }
            }
        }

        return $response;
    }
}
