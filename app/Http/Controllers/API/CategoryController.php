<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\CategoryRepositoryInterface;
use App\Http\Requests\Category\CategoryRequest;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function __construct(protected CategoryRepositoryInterface $categoryRepository)
    {
    }

    public function index()
    {
        return $this->categoryRepository->index();
    }

    public function store(CategoryRequest $request)
    {
        return $this->categoryRepository->store($request);
    }

    public function update(CategoryRequest $request, $id)
    {
        return $this->categoryRepository->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->categoryRepository->destroy($id);
    }
}
