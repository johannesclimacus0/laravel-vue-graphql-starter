<?php

namespace App\Http\Controllers\Api\Settings;

use App\Actions\Settings\DeleteUserAccount;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileDeleteRequest;
use Illuminate\Http\Response;

class DeleteAccountController extends Controller
{
    /**
     * Delete the authenticated user's account and invalidate the session.
     */
    public function __invoke(ProfileDeleteRequest $request, DeleteUserAccount $action): Response
    {
        $action->delete($request->user(), $request->validated(), $request);

        return response()->noContent();
    }
}
