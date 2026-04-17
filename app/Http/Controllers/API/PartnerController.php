<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PartnerRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PartnerController extends Controller
{
    public function __construct(
        protected PartnerRepositoryInterface $partnerRepository
    ) {
    }

    public function index(Request $request)
    {
        return $this->partnerRepository->index($request);
    }

    public function show($slug)
    {
        return $this->partnerRepository->show($slug);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'about' => 'required|string',
            'website' => 'nullable|url',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'focus_areas' => 'nullable|array',
            'impact' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->partnerRepository->store($request);
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'about' => 'sometimes|string',
            'website' => 'nullable|url',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'focus_areas' => 'nullable|array',
            'impact' => 'nullable|array'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        return $this->partnerRepository->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->partnerRepository->destroy($id);
    }
}
