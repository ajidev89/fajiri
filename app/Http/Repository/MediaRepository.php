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
            $url = $this->cloudinaryService->uploadVideo($file, 'media/videos');
        } else {
            $url = $this->cloudinaryService->uploadImage($file, 'media/images');
        }

        return $this->model->create([
            'user_id' => Auth::id(),
            'title' => $data['title'],
            'url' => $url,
            'type' => $type,
        ]);
    }

    public function delete($id)
    {
        $media = $this->model->where('user_id', Auth::id())->findOrFail($id);
        
        // Extract public_id from URL if not stored (though I added the field, I'm not populating it in store yet)
        // For now, just delete the DB record. 
        // Improvement: Store public_id during upload.
        
        return $media->delete();
    }
}
