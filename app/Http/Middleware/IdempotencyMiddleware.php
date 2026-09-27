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

        $endpoint    = $request->method().' '.$request->path();
        $requestHash = hash('sha256', json_encode($request->all()));


        try {
            IdempotencyKey::create([
                'key'          => $key,
                'endpoint'     => $endpoint,
                'request_hash' => $requestHash,
                'status'       => 'processing',
                'response_code' => null,
                'response_body' => null,
            ]);
        } catch (QueryException $e) {
            return $this->handleExistingKey($key, $endpoint, $requestHash);
        }

        $response = $next($request);

        if ($response->getStatusCode() < 500) {
            $content = json_decode($response->getContent(), true);

            IdempotencyKey::where('key', $key)
                ->where('endpoint', $endpoint)
                ->update([
                    'status'        => 'completed',
                    'response_code' => $response->getStatusCode(),
                    'response_body' => json_encode($content ?? []),
                ]);
        } else {
            IdempotencyKey::where('key', $key)
                ->where('endpoint', $endpoint)
                ->where('status', 'processing')
                ->delete();
        }

        return $response;
    }


    private function handleExistingKey(string $key, string $endpoint, string $requestHash): mixed
    {
        $attempts = 0;
        $maxAttempts = 10;
        $sleepMs = 100;

        while ($attempts < $maxAttempts) {
            $existing = IdempotencyKey::where('key', $key)
                ->where('endpoint', $endpoint)
                ->first();

            if (! $existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'The original request failed. You may retry with the same Idempotency-Key.',
                ], Response::HTTP_SERVICE_UNAVAILABLE);
            }

            if ($existing->status === 'completed') {
                if ($existing->request_hash !== $requestHash) {
                    return $this->errorResponse(
                        message: 'This Idempotency-Key has already been used with a different request payload.',
                        statusCode: Response::HTTP_UNPROCESSABLE_ENTITY
                    );
                }

                return response()->json(
                    $existing->response_body,
                    $existing->response_code
                );
            }

            $attempts++;
            usleep($sleepMs * 1000);
        }

        return response()->json([
            'success' => false,
            'message' => 'A request with this Idempotency-Key is currently being processed. Please retry shortly.',
        ], Response::HTTP_CONFLICT);
    }
}
