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
        $results = [];

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

            $results[] = [
                'url' => $upload['secure_url'],
                'public_id' => $upload['public_id'],
            ];
        }

        return $results;
    }

    /**
     * Upload single image file
     */
    public function uploadImage(UploadedFile $file, string $folder = 'uploads'): array
    {
        $upload = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $folder,
                'resource_type' => 'image',
            ]
        );

        return [
            'url' => $upload['secure_url'],
            'public_id' => $upload['public_id'],
        ];
    }

    /**
     * Upload video file
     */
    public function uploadVideo(UploadedFile $file, string $folder = 'videos'): array
    {
      
        $upload = $this->cloudinary->uploadApi()->upload(
            $file->getRealPath(),
            [
                'folder' => $folder,
                'resource_type' => 'video',
            ]
        );

        return [
            'url' => $upload['secure_url'],
            'public_id' => $upload['public_id'],
        ];
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
