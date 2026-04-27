<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShelterOnboardingRequest;
use App\Services\ShelterOnboardingService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ShelterOnboardingController extends Controller
{
    public function __construct(
        private readonly ShelterOnboardingService $onboardingService
    ) {
    }

    #[OA\Post(
        path: "/api/shelters/onboarding",
        tags: ["ShelterOnboarding"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "ShelterOnboardingRequest",
                properties: [
                    new OA\Property(property: "organization_name", type: "string"),
                    new OA\Property(property: "website", type: "string"),
                    new OA\Property(property: "ein_tax_id", type: "string"),
                    new OA\Property(property: "physical_address", type: "string"),
                    new OA\Property(property: "bio_mission", type: "string"),
                    new OA\Property(property: "logo", type: "string"),
                    new OA\Property(
                        property: "verification_docs",
                        type: "array",
                        items: new OA\Items(type: "string")
                    ),
                    new OA\Property(property: "verification_docs.*", type: "string"),
                    new OA\Property(property: "shelter_type", type: "string"),
                    new OA\Property(property: "max_capacity", type: "integer"),
                    new OA\Property(property: "facilities", type: "string"),
                    new OA\Property(property: "operating_hours", type: "string"),
                    new OA\Property(property: "services_offered", type: "string"),
                    new OA\Property(property: "adoption_requirements", type: "string"),
                    new OA\Property(property: "latitude", type: "number"),
                    new OA\Property(property: "longitude", type: "number"),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "string"),
                        new OA\Property(property: "message", type: "string"),
                        new OA\Property(property: "shelter", type: "string"),
                    ]
                )
            )
        ]
    )]
    public function store(ShelterOnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $shelter = $this->onboardingService->upsertForUser(
            $user,
            $request->validated(),
            $request->file('logo'),
            $request->file('verification_docs', []),
        );

        return response()->json([
            'success' => true,
            'message' => 'Shelter onboarding submitted successfully.',
            'shelter' => $shelter,
        ], 200);
    }
}

