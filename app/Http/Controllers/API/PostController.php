<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\PostRepositoryInterface;
use App\Http\Requests\Post\PostRequest;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function __construct(protected PostRepositoryInterface $postRepository)
    {
    }

    public function index(Request $request)
    {
        return $this->postRepository->index($request);
    }

    public function show($slug)
    {
        return $this->postRepository->show($slug);
    }

    public function store(PostRequest $request)
    {
        return $this->postRepository->store($request);
    }

    public function update(PostRequest $request, $id)
    {
        return $this->postRepository->update($request, $id);
    }

    public function destroy($id)
    {
        return $this->postRepository->destroy($id);
    }
}
