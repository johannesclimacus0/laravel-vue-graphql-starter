<?php

namespace App\Http\Controllers\Api\Settings;

use App\Actions\Settings\UpdateUserPreferences;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferencesController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'timezone' => $request->user()->timezone,
            ],
        ]);
    }

    public function update(Request $request, UpdateUserPreferences $action): JsonResponse
    {
        $user = $request->user();
        $action->update($user, $request->all());

        return response()->json([
            'data' => [
                'timezone' => $user->timezone,
            ],
        ]);
    }
}
