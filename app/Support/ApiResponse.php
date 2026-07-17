<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;
use JsonSerializable;

final class ApiResponse
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, string|array<int, string>> $errors
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operación realizada correctamente.',
        int $status = 200,
        array $meta = [],
    ): JsonResponse {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => self::normalize($data),
        ];

        if ($meta !== []) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $status);
    }

    /**
     * @param array<string, string|array<int, string>> $errors
     */
    public static function error(
        string $message,
        int $status = 400,
        ?string $code = null,
        array $errors = [],
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($code !== null && $code !== '') {
            $response['code'] = $code;
        }

        if ($errors !== []) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $status);
    }

    private static function normalize(mixed $data): mixed
    {
        if ($data instanceof Arrayable) {
            return $data->toArray(request());
        }

        if ($data instanceof JsonSerializable) {
            return $data->jsonSerialize();
        }

        return $data;
    }

    private function __construct()
    {
    }
}
