<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PartnerRepositoryInterface;
use App\Http\Resources\PartnerResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Models\Partner;

class PartnerRepository implements PartnerRepositoryInterface
{
    use ResponseTrait;

    public function __construct(
        protected Partner $partner,
        protected CloudinaryService $cloudinaryService
    ) {
    }

    public function index($request)
    {
        $partners = $this->partner->latest()
            ->paginate($request->per_page ?? 15);

        return $this->handleSuccessCollectionResponse("Partners fetched successfully", PartnerResource::collection($partners));
    }

    public function show($slug)
    {
        try {
            $partner = $this->partner->where('slug', $slug)->firstOrFail();
            return $this->handleSuccessResponse("Partner fetched successfully", new PartnerResource($partner));
        } catch (\Exception $e) {
            return $this->handleErrorResponse("Partner not found", 404);
        }
    }

    public function store($request)
    {
        try {
            $logoUrl = null;
            if ($request->hasFile('logo')) {
                $logoUrl = $this->cloudinaryService->uploadImage($request->file('logo'), 'partners');
            }

            $partner = $this->partner->create([
                'name' => $request->name,
                'about' => $request->about,
                'website' => $request->website,
                'focus_areas' => $request->focus_areas,
                'impact' => $request->impact,
                'logo' => $logoUrl,
            ]);

            return $this->handleSuccessResponse("Partner created successfully", new PartnerResource($partner));
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function update($request, $id)
    {
        try {
            $partner = $this->partner->findOrFail($id);
            
            $data = $request->only(['name', 'about', 'website', 'focus_areas', 'impact']);

            if ($request->hasFile('logo')) {
                $data['logo'] = $this->cloudinaryService->uploadImage($request->file('logo'), 'partners');
            }

            $partner->update($data);

            return $this->handleSuccessResponse("Partner updated successfully", new PartnerResource($partner));
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $partner = $this->partner->findOrFail($id);
            $partner->delete();

            return $this->handleSuccessResponse("Partner deleted successfully");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }
}
