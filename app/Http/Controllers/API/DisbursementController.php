<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\DisbursementRepositoryInterface;
use App\Http\Requests\Disbursement\DisburseRequest;
use App\Http\Requests\Disbursement\StoreRequest;
use App\Http\Resources\Disbursement\DisbursementResource;
use Illuminate\Http\Request;

class DisbursementController extends Controller
{
    protected $disbursementRepository;

    public function __construct(DisbursementRepositoryInterface $disbursementRepository)
    {
        $this->disbursementRepository = $disbursementRepository;
    }

    public function index()
    {
        $disbursements = $this->disbursementRepository->all();
        return DisbursementResource::collection($disbursements);
    }

    public function show($id)
    {
        $disbursement = $this->disbursementRepository->find($id);
        return new DisbursementResource($disbursement);
    }

    public function store(StoreRequest $request)
    {
        $disbursement = $this->disbursementRepository->request($request->validated());

        return $this->handleSuccessResponse('Disbursement request submitted successfully', [
            'data' => new DisbursementResource($disbursement),
        ], 201);
    }

    public function disburse(DisburseRequest $request, $id)
    {
        try {
            $disbursement = $this->disbursementRepository->disburse($id, $request->file('proof_of_payment'));

            return $this->handleSuccessResponse('Disbursement completed successfully', [
                'data' => new DisbursementResource($disbursement),
            ]);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function reject(Request $request, $id)
    {
        $request->validate([
            'rejected_reason' => 'required|string',
        ]);

        try {
            $disbursement = $this->disbursementRepository->reject($id, $request->rejected_reason);

            return $this->handleSuccessResponse('Disbursement request rejected', [
                'data' => new DisbursementResource($disbursement),
            ]);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}
