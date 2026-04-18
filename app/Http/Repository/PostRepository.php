<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\PostRepositoryInterface;
use App\Http\Resources\Blog\PostResource;
use App\Http\Services\CloudinaryService;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\AuthUserTrait;
use App\Models\Post;
use Illuminate\Support\Str;

class PostRepository implements PostRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function __construct(protected Post $post, protected CloudinaryService $cloudinaryService)
    {
    }

    public function index($request)
    {
        $posts = $this->post->with(['category', 'user'])
            ->when($request->category_id, function ($query) use ($request) {
                return $query->where('category_id', $request->category_id);
            })
            ->when($request->status, function ($query) use ($request) {
                return $query->where('status', $request->status);
            })
            ->when($request->is_featured, function ($query) use ($request) {
                return $query->where('is_featured', $request->is_featured);
            })
            ->latest()
            ->paginate($request->per_page ?? 15);

        return $this->handleSuccessCollectionResponse("Posts fetched successfully", PostResource::collection($posts));
    }

    public function show($slug)
    {
        try {
            $post = $this->post->with(['category', 'user'])->where('slug', $slug)->firstOrFail();
            return $this->handleSuccessResponse("Post fetched successfully", new PostResource($post));
        } catch (\Exception $e) {
            return $this->handleErrorResponse("Post not found", 404);
        }
    }

    public function store($request)
    {
        try {
            $imageUrl = null;
            if ($request->hasFile('image')) {
                $imageUrl = $this->cloudinaryService->uploadImage($request->file('image'), 'blogs');
            }

            $post = $this->post->create([
                'user_id' => $this->user()->id,
                'category_id' => $request->category_id,
                'title' => $request->title,
                'slug' => Str::slug($request->title) . '-' . Str::random(5),
                'content' => $request->content,
                'image' => $imageUrl,
                'status' => $request->status ?? 'draft',
                'is_featured' => $request->is_featured ?? false,
                'published_at' => ($request->status == 'published') ? now() : null,
            ]);

            return $this->handleSuccessResponse("Post created successfully", new PostResource($post));
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function update($request, $id)
    {
        try {
            $post = $this->post->findOrFail($id);
            
            $data = [
                'category_id' => $request->category_id ?? $post->category_id,
                'title' => $request->title ?? $post->title,
                'content' => $request->content ?? $post->content,
                'status' => $request->status ?? $post->status,
                'is_featured' => $request->is_featured ?? $post->is_featured,
            ];

            if ($request->title) {
                $data['slug'] = Str::slug($request->title) . '-' . Str::random(5);
            }

            if ($request->hasFile('image')) {
                $data['image'] = $this->cloudinaryService->uploadImage($request->file('image'), 'blogs');
            }

            if ($request->status == 'published' && $post->status != 'published') {
                $data['published_at'] = now();
            }

            $post->update($data);

            return $this->handleSuccessResponse("Post updated successfully", new PostResource($post));
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function destroy($id)
    {
        try {
            $post = $this->post->findOrFail($id);
            $post->delete();

            return $this->handleSuccessResponse("Post deleted successfully");
        } catch (\Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }
}
