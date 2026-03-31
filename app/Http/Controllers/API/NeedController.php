<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\NeedRepositoryInterface;
use App\Http\Requests\Need\CreateRequest;
use App\Http\Resources\Need\NeedResource;
use App\Models\Need;
use Illuminate\Http\Request;

class NeedController extends Controller
{
    public function __construct(private NeedRepositoryInterface $needRepository)
    {
    }

    public function index()
    {
        return $this->handleSuccessCollectionResponse('Needs fetched successfully',  NeedResource::collection( $this->needRepository->index()));
    }

    public function find(Need $need)
    {
        return $this->handleSuccessResponse('Need fetched successfully', new NeedResource($this->needRepository->find($need)));
    }

    public function create(CreateRequest $request)
    {
        return $this->handleSuccessResponse('Need created successfully', new NeedResource($this->needRepository->create($request->validated())));
    }

    public function update(Need $need, CreateRequest $request)
    {
        return $this->handleSuccessResponse('Need updated successfully', new NeedResource($this->needRepository->update($need, $request->validated())));
    }

    public function delete(Need $need)
    {
        $this->needRepository->delete($need);
        return $this->handleSuccessResponse('Need deleted successfully');
    }
}
