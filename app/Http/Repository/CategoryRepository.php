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

    public function index()
    {
        $categories = $this->category->latest()->get();
        return $this->handleSuccessResponse("Categories fetched successfully", $categories);
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
