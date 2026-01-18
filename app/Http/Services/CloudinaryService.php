<?php

namespace App\Http\Services;

use Cloudinary\Cloudinary;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Cloudinary Service
 */
class CloudinaryService
{
    protected Cloudinary $cloudinary;

    public function __construct()
    {
        $this->cloudinary = new Cloudinary([
            'cloud' => [
                'cloud_name' => config('services.cloudinary.cloud_name'),
                'api_key'    => config('services.cloudinary.api_key'),
                'api_secret' => config('services.cloudinary.api_secret'),
            ],
            'url' => ['secure' => true],
        ]);
    }

    /**
     * Upload multiple image files
     */
    public function uploadMultiple(array $files, string $folder = 'uploads'): array
    {
        $urls = [];

        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                throw new RuntimeException('Invalid file provided');
            }

            $upload = $this->cloudinary->uploadApi()->upload(
                $file->getRealPath(),
                [
                    'folder' => $folder,
                    'resource_type' => 'image',
                ]
            );

            $urls[] = $upload['secure_url'];
        }

        return $urls;
    }

    /**
     * Upload single image file
     */
    public function uploadImage(UploadedFile $file, string $folder = 'uploads'): string
    {
        $upload = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $folder,
                'resource_type' => 'image',
            ]
        );

        return $upload['secure_url'];
    }

    /**
     * Upload video file
     */
    public function uploadVideo(UploadedFile $file, string $folder = 'videos'): string
    {
      
        $upload = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $folder,
                'resource_type' => 'video',
            ]
        );

        return $upload['secure_url'];
    }

    /**
     * Delete uploaded file
     */
    public function delete(string $publicId, string $resourceType = 'image')
    {
        return $this->cloudinary->uploadApi()->destroy(
            $publicId,
            ['resource_type' => $resourceType]
        );
    }
}
