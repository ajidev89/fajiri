<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PreferenceRepositoryInterface;
use App\Http\Requests\User\PreferenceUpdateRequest;
use Illuminate\Http\Request;

class PreferenceController extends Controller
{
    public function __construct(protected PreferenceRepositoryInterface $preferenceRepository)
    {}

    /**
     * Get user preferences.
     */
    public function index(Request $request)
    {
        $preferences = $this->preferenceRepository->getForUser($request->user()->id);
        return response()->json([
            'status' => 'success',
            'data' => $preferences
        ]);
    }

    /**
     * Update user preferences.
     */
    public function update(PreferenceUpdateRequest $request)
    {
        $preferences = $this->preferenceRepository->updateForUser($request->user()->id, $request->validated());
        return response()->json([
            'status' => 'success',
            'message' => 'Preferences updated successfully',
            'data' => $preferences
        ]);
    }
}
