<?php

namespace App\Http\Controllers;

use App\Models\Shelter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class ShelterPublicController extends Controller
{
    #[OA\Get(
        path: "/api/shelters/nearby",
        tags: ["ShelterPublic"],
        summary: "Auto generated endpoint",
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "shelters", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function nearby(Request $request): JsonResponse
    {
        $lat = is_numeric($request->query('lat')) ? (float) $request->query('lat') : null;
        $lng = is_numeric($request->query('lng')) ? (float) $request->query('lng') : null;
        $radiusKm = is_numeric($request->query('radiusKm')) ? (float) $request->query('radiusKm') : 100.0;

        $query = Shelter::query()
            ->with('addressRecord')
            ->whereNotNull('address_id');

        $rows = $query->get()->map(function (Shelter $shelter): ?array {
            $address = $shelter->addressRecord;
            if (! $address || $address->latitude === null || $address->longitude === null) {
                return null;
            }

            return [
                'id' => (string) $shelter->id,
                'name' => (string) ($shelter->organization_name ?: $shelter->shelter_name),
                'address' => (string) ($shelter->physical_address ?: $shelter->address ?: ''),
                'latitude' => (float) $address->latitude,
                'longitude' => (float) $address->longitude,
            ];
        })->filter()->values();

        if ($lat !== null && $lng !== null) {
            $rows = $rows->filter(function (array $row) use ($lat, $lng, $radiusKm): bool {
                return $this->distanceKm(
                    $lat,
                    $lng,
                    (float) $row['latitude'],
                    (float) $row['longitude'],
                ) <= $radiusKm;
            })->values();
        }

        return response()->json([
            'shelters' => $rows,
        ]);
    }

    #[OA\Get(
        path: "/api/shelters/{id}",
        tags: ["ShelterPublic"],
        summary: "Auto generated endpoint",
        parameters: [
            new OA\Parameter(
                name: "id",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "shelter", type: "string"),
                        new OA\Property(property: "id", type: "string"),
                        new OA\Property(property: "name", type: "string"),
                        new OA\Property(property: "address", type: "string"),
                        new OA\Property(property: "latitude", type: "number"),
                        new OA\Property(property: "longitude", type: "number"),
                    ]
                )
            )
        ]
    )]
    public function show(string $id): JsonResponse
    {
        $shelter = Shelter::query()->with('addressRecord')->find($id);
        if (! $shelter) {
            return response()->json(['message' => 'Shelter not found.'], 404);
        }

        $address = $shelter->addressRecord;
        return response()->json([
            'shelter' => [
                'id' => (string) $shelter->id,
                'name' => (string) ($shelter->organization_name ?: $shelter->shelter_name),
                'address' => (string) ($shelter->physical_address ?: $shelter->address ?: ''),
                'latitude' => $address?->latitude !== null ? (float) $address->latitude : null,
                'longitude' => $address?->longitude !== null ? (float) $address->longitude : null,
            ],
        ]);
    }

    private function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth * $c;
    }
}

