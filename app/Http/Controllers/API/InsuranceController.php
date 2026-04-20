<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\InsuranceRepositoryInterface;
use App\Http\Requests\Insurance\CreateRequest;
use App\Http\Resources\Insurance\InsuranceResource;
use Illuminate\Http\Request;

class InsuranceController extends Controller
{
    public function __construct(private InsuranceRepositoryInterface $insuranceRepository) {}

    public function index(Request $request)
    {
        return InsuranceResource::collection($this->insuranceRepository->index($request));
    }

    public function all()
    {
        return InsuranceResource::collection($this->insuranceRepository->all());
    }

    public function find($id)
    {
        return new InsuranceResource($this->insuranceRepository->find($id));
    }

    public function create(CreateRequest $request)
    {
        return new InsuranceResource($this->insuranceRepository->create($request->all()));
    }

    public function update($id, Request $request)
    {
        return new InsuranceResource($this->insuranceRepository->update($id, $request->all()));
    }

    public function delete($id)
    {
        return $this->insuranceRepository->delete($id);
    }
}
