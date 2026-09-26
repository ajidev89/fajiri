<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\AmbassadorRepositoryInterface;
use App\Http\Requests\Ambassador\AmbassadorRequest;
use Illuminate\Http\Request;

class AmbassadorController extends Controller
{
    public function __construct(protected AmbassadorRepositoryInterface $ambassadorRepository) {}

    public function index(Request $request)
    {
        return $this->ambassadorRepository->index($request);
    }

    public function show(string $slug)
    {
        return $this->ambassadorRepository->show($slug);
    }

    public function store(AmbassadorRequest $request)
    {
        return $this->ambassadorRepository->store($request);
    }

    public function update(AmbassadorRequest $request, string $id)
    {
        return $this->ambassadorRepository->update($request, $id);
    }

    public function destroy(string $id)
    {
        return $this->ambassadorRepository->destroy($id);
    }
}
