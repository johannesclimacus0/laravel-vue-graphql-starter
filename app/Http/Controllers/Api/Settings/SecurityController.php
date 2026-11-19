<?php

namespace App\Http\Controllers\Api\Settings;

use App\Actions\Settings\GetUserSecuritySettings;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __invoke(Request $request, GetUserSecuritySettings $action): JsonResponse
    {
        return response()->json(['data' => $action->get($request->user())]);
    }
}
