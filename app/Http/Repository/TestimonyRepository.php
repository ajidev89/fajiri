<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\TestimonyRepositoryInterface;
use App\Http\Resources\Testimony\TestimonyResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Models\Testimony;

class TestimonyRepository implements TestimonyRepositoryInterface
{
    use ResponseTrait;

    public function __construct(
        protected Testimony $testimony,
        protected CloudinaryService $cloudinaryService,
    ) {}

    public function index($request)
    {
        $query = $this->testimony->newQuery();

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($builder) use ($term) {
                $builder->where('name', 'like', "%{$term}%")
                    ->orWhere('story', 'like', "%{$term}%");
            });
        }

        $allowedSorts = ['created_at', 'updated_at', 'name', 'age', 'sort_order'];
        $sortBy = in_array($request->input('sort_by'), $allowedSorts, true)
            ? $request->input('sort_by')
            : 'sort_order';
        $defaultOrder = $sortBy === 'sort_order' ? 'asc' : 'desc';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', $defaultOrder)), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', $defaultOrder))
            : $defaultOrder;

        $testimonies = $query->orderBy($sortBy, $sortOrder)
            ->orderBy('name')
            ->paginate($request->integer('per_page', 15));

        return $this->handleSuccessCollectionResponse(
            'Testimonies fetched successfully',
            TestimonyResource::collection($testimonies)
        );
    }

    public function show(string $slug)
    {
        $testimony = $this->testimony->newQuery()->where('slug', $slug)->first();

        if ($testimony === null) {
            return $this->handleErrorResponse('Testimony not found', 404);
        }

        return $this->handleSuccessResponse(
            'Testimony fetched successfully',
            new TestimonyResource($testimony)
        );
    }

    public function store($request)
    {
        $upload = $this->cloudinaryService->uploadImage($request->file('photo'), 'testimonies');

        $testimony = $this->testimony->create([
            'name' => $request->name,
            'age' => $request->integer('age'),
            'story' => $request->story,
            'photo' => $upload['url'],
            'sort_order' => $request->integer('sort_order', 0),
        ]);

        return $this->handleSuccessResponse(
            'Testimony created successfully',
            new TestimonyResource($testimony)
        );
    }

    public function update($request, string $id)
    {
        $testimony = $this->testimony->newQuery()->findOrFail($id);
        $data = $request->only(['name', 'age', 'story', 'sort_order']);

        if ($request->hasFile('photo')) {
            $upload = $this->cloudinaryService->uploadImage($request->file('photo'), 'testimonies');
            $data['photo'] = $upload['url'];
        }

        $testimony->update($data);

        return $this->handleSuccessResponse(
            'Testimony updated successfully',
            new TestimonyResource($testimony)
        );
    }

    public function destroy(string $id)
    {
        $testimony = $this->testimony->newQuery()->findOrFail($id);
        $testimony->delete();

        return $this->handleSuccessResponse('Testimony deleted successfully');
    }
}
