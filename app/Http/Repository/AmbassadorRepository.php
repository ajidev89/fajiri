<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\AmbassadorRepositoryInterface;
use App\Http\Resources\Ambassador\AmbassadorResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Models\Ambassador;

class AmbassadorRepository implements AmbassadorRepositoryInterface
{
    use ResponseTrait;

    public function __construct(
        protected Ambassador $ambassador,
        protected CloudinaryService $cloudinaryService,
    ) {}

    public function index($request)
    {
        $query = $this->ambassador->newQuery();

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%")
                    ->orWhere('biography', 'like', "%{$term}%");
            });
        }

        $allowedSorts = ['created_at', 'updated_at', 'name', 'title', 'sort_order'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'sort_order';
        $defaultOrder = $sortBy === 'sort_order' ? 'asc' : 'desc';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', $defaultOrder)), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', $defaultOrder))
            : $defaultOrder;

        $ambassadors = $query->orderBy($sortBy, $sortOrder)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->handleSuccessCollectionResponse(
            'Ambassadors fetched successfully',
            AmbassadorResource::collection($ambassadors)
        );
    }

    public function show(string $slug)
    {
        $ambassador = $this->ambassador->newQuery()->where('slug', $slug)->first();

        if ($ambassador === null) {
            return $this->handleErrorResponse('Ambassador not found', 404);
        }

        return $this->handleSuccessResponse(
            'Ambassador fetched successfully',
            new AmbassadorResource($ambassador)
        );
    }

    public function store($request)
    {
        $upload = $this->cloudinaryService->uploadImage($request->file('photo'), 'ambassadors');

        $ambassador = $this->ambassador->create([
            'name' => $request->name,
            'title' => $request->title,
            'biography' => $request->biography,
            'photo' => $upload['url'],
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return $this->handleSuccessResponse(
            'Ambassador created successfully',
            new AmbassadorResource($ambassador)
        );
    }

    public function update($request, string $id)
    {
        $ambassador = $this->ambassador->newQuery()->findOrFail($id);
        $data = $request->only(['name', 'title', 'biography', 'sort_order']);

        if ($request->hasFile('photo')) {
            $upload = $this->cloudinaryService->uploadImage($request->file('photo'), 'ambassadors');
            $data['photo'] = $upload['url'];
        }

        $ambassador->update($data);

        return $this->handleSuccessResponse(
            'Ambassador updated successfully',
            new AmbassadorResource($ambassador)
        );
    }

    public function destroy(string $id)
    {
        $ambassador = $this->ambassador->newQuery()->findOrFail($id);
        $ambassador->delete();

        return $this->handleSuccessResponse('Ambassador deleted successfully');
    }
}
