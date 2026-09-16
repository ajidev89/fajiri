<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Announcement\AnnouncementResource;
use App\Jobs\SendGlobalAnnouncementJob;
use App\Models\Announcement;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::latest()->paginate(15);

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
