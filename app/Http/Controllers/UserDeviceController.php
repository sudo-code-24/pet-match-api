<?php

namespace App\Http\Controllers;

use App\Models\UserDevice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Attributes as OA;

class UserDeviceController extends Controller
{
    /**
     * @deprecated Prefer POST /api/devices/expo-push-token with Expo tokens from getExpoPushTokenAsync.
     */
    #[OA\Post(
        path: "/api/devices/fcm-token",
        tags: ["UserDevice"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "RequestPayload",
                additionalProperties: true
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", nullable: true, example: "Request processed successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            title: "ResponseData",
                            additionalProperties: true
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            nullable: true,
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ]
    )]
    public function storeFcmToken(Request $request): JsonResponse
    {
        return $this->storeDevicePushToken($request, 'device_token');
    }

    #[OA\Post(
        path: "/api/devices/expo-push-token",
        tags: ["UserDevice"],
        summary: "Auto generated endpoint",
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                type: "object",
                title: "RequestPayload",
                additionalProperties: true
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Success",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", nullable: true, example: "Request processed successfully"),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            title: "ResponseData",
                            additionalProperties: true
                        ),
                        new OA\Property(
                            property: "meta",
                            type: "object",
                            nullable: true,
                            additionalProperties: true
                        ),
                    ]
                )
            )
        ]
    )]
    public function storeExpoPushToken(Request $request): JsonResponse
    {
        return $this->storeDevicePushToken($request, 'expo_push_token');
    }

    /**
     * @param  'device_token'|'expo_push_token'  $field
     */
    private function storeDevicePushToken(Request $request, string $field): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! is_string($user->id) || $user->id === '') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            $field => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:32'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $token = trim((string) $validator->validated()[$field]);
        $platform = isset($validator->validated()['platform'])
            ? trim((string) $validator->validated()['platform'])
            : null;

        if ($field === 'expo_push_token' && ! preg_match('/^ExponentPushToken\\[[^\\]]+\\]$/', $token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Expo push token format.',
            ], 422);
        }

        UserDevice::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_token' => $token,
            ],
            [
                'platform' => $platform !== '' ? $platform : null,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Device registered.',
        ]);
    }
}
