<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\MediaRepositoryInterface;
use App\Http\Services\CloudinaryService;
use App\Models\Media;
use Illuminate\Support\Facades\Auth;
use Exception;

class MediaRepository implements MediaRepositoryInterface
{
    public function __construct(protected Media $model, protected CloudinaryService $cloudinaryService)
    {
    }

    public function index()
    {
        return $this->model->where('user_id', Auth::id())->latest()->get();
    }

    public function store(array $data, $file)
    {
        $mimeType = $file->getMimeType();
        $type = str_contains($mimeType, 'video') ? 'video' : 'image';

        if ($type === 'video') {
            $upload = $this->cloudinaryService->uploadVideo($file, 'media/videos');
        } else {
            $upload = $this->cloudinaryService->uploadImage($file, 'media/images');
        }

        return $this->model->create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'url' => $upload['url'],
            'public_id' => $upload['public_id'],
            'type' => $type,
        ]);
    }

    public function delete($id)
    {
        $media = $this->model->where('user_id', Auth::id())->findOrFail($id);
        
        if ($media->public_id) {
            try {
                $this->cloudinaryService->delete($media->public_id, $media->type);
            } catch (Exception $e) {
                // Log error but continue with DB deletion
                report($e);
            }
        }
        
        return $media->delete();
    }
}
