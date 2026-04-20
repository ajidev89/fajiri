<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\MediaRepositoryInterface;
use Illuminate\Http\Request;

class MediaController extends Controller
{
    public function __construct(protected MediaRepositoryInterface $mediaRepository)
    {
    }

    public function index()
    {
        $media = $this->mediaRepository->index();
        return $this->handleSuccessResponse('Media fetched successfully', $media);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'file' => 'required|file|mimes:jpg,jpeg,png,mp4,mov,avi|max:51200', // 50MB max
        ]);

        try {
            $media = $this->mediaRepository->store($request->only('title'), $request->file('file'));
            return $this->handleSuccessResponse('Media uploaded successfully', $media);
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }

    public function destroy($id)
    {
        try {
            $this->mediaRepository->delete($id);
            return $this->handleSuccessResponse('Media deleted successfully');
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage(), 400);
        }
    }
}
