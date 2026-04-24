<?php

namespace App\Http\Controllers;

use App\Http\Requests\ShelterOnboardingRequest;
use App\Services\ShelterOnboardingService;
use Illuminate\Http\JsonResponse;

class ShelterOnboardingController extends Controller
{
    public function __construct(
        private readonly ShelterOnboardingService $onboardingService
    ) {
    }

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

