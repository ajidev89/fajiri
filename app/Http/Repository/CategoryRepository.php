<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\CategoryRepositoryInterface;
use App\Http\Traits\ResponseTrait;
use App\Models\Category;
use Illuminate\Support\Str;

class CategoryRepository implements CategoryRepositoryInterface
{
    use ResponseTrait;

    public function __construct(protected Category $category)
    {
    }

    public function index($request = null)
    {
        $query = $this->category->newQuery();

        if ($request && $request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('description', 'like', "%{$term}%");
            });
        }

        $sortBy = $request && in_array($request->input('sort_by'), ['created_at', 'updated_at', 'name'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = $request && in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        $sorted = $query->orderBy($sortBy, $sortOrder);

        if ($request && $request->filled('page')) {
            $paginated = $sorted->paginate($request->per_page ?? 15);
            return response()->json([
                'message' => 'Categories fetched successfully',
                'status' => true,
                'data' => $paginated->items(),
                'meta' => [
                    'current_page' => $paginated->currentPage(),
                    'last_page' => $paginated->lastPage(),
                    'per_page' => $paginated->perPage(),
                    'total' => $paginated->total(),
                ],
            ]);
        }

        return $this->handleSuccessResponse("Categories fetched successfully", $sorted->get());
    }

    public function store($request)
    {
        try {
            $category = $this->category->create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
            ]);

            return $this->handleSuccessResponse("Category created successfully", $category);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function update($request, $id)
    {
        try {
            $category = $this->category->findOrFail($id);
            $category->update([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'description' => $request->description,
            ]);

            return $this->handleSuccessResponse("Category updated successfully", $category);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $category = $this->category->findOrFail($id);
            $category->delete();

            return $this->handleSuccessResponse("Category deleted successfully");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }
}
