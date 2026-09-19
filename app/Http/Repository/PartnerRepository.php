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
        $partners = $this->partner
            ->when($request->country_id, function ($query) use ($request) {
                return $query->where('country_id', $request->country_id);
            })
            ->when($request->filled('search'), function ($query) use ($request) {
                $term = $request->input('search');
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                        ->orWhere('website', 'like', "%{$term}%");
                });
            });

        $sortBy = in_array($request->input('sort_by'), ['created_at', 'updated_at', 'name'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        $partners = $partners->orderBy($sortBy, $sortOrder)
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
                $upload = $this->cloudinaryService->uploadImage($request->file('logo'), 'partners');
                $logoUrl = $upload['url'];
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
                $upload = $this->cloudinaryService->uploadImage($request->file('logo'), 'partners');
                $data['logo'] = $upload['url'];
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
