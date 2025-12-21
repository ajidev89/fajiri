<?php

namespace App\Http\Traits;

use Exception;
use Illuminate\Http\Resources\Json\ResourceCollection;

trait ResponseTrait
{
    public function handleSuccessResponse($message, $data = [], $statusCode = 200)
    {

        try {
            return response()->json([
                'message' => $message,
                'data' => $data,
                'status' => true,
            ], $statusCode);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    public function handleSuccessCollectionResponse($message, ResourceCollection $resourceCollection)
    {

        try {
            return $resourceCollection->additional([
                'message' => $message,
                'status' => true,
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    public function handleErrorResponse($message, $statusCode = 400)
    {

        return response()->json([
            'message' => $message,
            'data' => null,
            'status' => false,
        ], $statusCode);
    }
}
