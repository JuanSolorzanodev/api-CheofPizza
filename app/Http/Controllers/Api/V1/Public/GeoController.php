<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Services\Geo\ReverseGeocodingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class GeoController
{
    /**
     * Devuelve una dirección aproximada a partir de coordenadas.
     */
    public function reverse(
        Request $request,
        ReverseGeocodingService $service,
    ): JsonResponse {
        $validated = $request->validate(
            [
                'lat' => [
                    'required',
                    'numeric',
                    'between:-90,90',
                ],

                'lng' => [
                    'required',
                    'numeric',
                    'between:-180,180',
                ],
            ],
            [
                'lat.required' => 'La latitud es obligatoria.',
                'lat.numeric' => 'La latitud debe ser numérica.',
                'lat.between' => 'La latitud debe estar entre -90 y 90.',

                'lng.required' => 'La longitud es obligatoria.',
                'lng.numeric' => 'La longitud debe ser numérica.',
                'lng.between' => 'La longitud debe estar entre -180 y 180.',
            ],
        );

        $result = $service->reverse(
            latitude: (float) $validated['lat'],
            longitude: (float) $validated['lng'],
        );

        return response()->json([
            'data' => $result,
        ]);
    }
}
