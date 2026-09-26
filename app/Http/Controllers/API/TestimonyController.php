<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\TestimonyRepositoryInterface;
use App\Http\Requests\Testimony\TestimonyRequest;
use Illuminate\Http\Request;

class TestimonyController extends Controller
{
    public function __construct(protected TestimonyRepositoryInterface $testimonyRepository) {}

    public function index(Request $request)
    {
        return $this->testimonyRepository->index($request);
    }

    public function show(string $slug)
    {
        return $this->testimonyRepository->show($slug);
    }

    public function store(TestimonyRequest $request)
    {
        return $this->testimonyRepository->store($request);
    }

    public function update(TestimonyRequest $request, string $id)
    {
        return $this->testimonyRepository->update($request, $id);
    }

    public function destroy(string $id)
    {
        return $this->testimonyRepository->destroy($id);
    }
}
