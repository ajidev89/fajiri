<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Announcement\AnnouncementResource;
use App\Jobs\SendGlobalAnnouncementJob;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = Announcement::query();

        if ($request->filled('search')) {
            $term = $request->input('search');
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                    ->orWhere('content', 'like', "%{$term}%");
            });
        }

        $sortBy = in_array($request->input('sort_by'), ['created_at', 'updated_at', 'title'], true)
            ? $request->input('sort_by')
            : 'created_at';
        $sortOrder = in_array(strtolower((string) $request->input('sort_order', 'desc')), ['asc', 'desc'], true)
            ? strtolower((string) $request->input('sort_order', 'desc'))
            : 'desc';

        $announcements = $query->orderBy($sortBy, $sortOrder)
            ->paginate($request->per_page ?? 15);

        return $this->handleSuccessCollectionResponse(
            'Successfully fetched announcements',
            AnnouncementResource::collection($announcements)
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image_url' => 'nullable|url',
            'target_audience' => 'nullable|array',
            'target_audience.*' => 'string',
        ]);

        $announcement = Announcement::create($validated);

        SendGlobalAnnouncementJob::dispatch($announcement);

        return $this->handleSuccessResponse(
            'Announcement created and notifications dispatched',
            new AnnouncementResource($announcement)
        );
    }
}
