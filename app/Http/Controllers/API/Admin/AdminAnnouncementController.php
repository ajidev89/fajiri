<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Jobs\SendGlobalAnnouncementJob;
use Illuminate\Http\Request;

class AdminAnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::latest()->paginate(15);
        return $this->handleSuccessCollectionResponse("Successfully fetched announcements", $announcements);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image_url' => 'nullable|url',
        ]);

        $announcement = Announcement::create($validated);

        // Dispatch the job to send push notifications in the background
        SendGlobalAnnouncementJob::dispatch($announcement);

        return $this->handleSuccessResponse("Announcement created and notifications dispatched", $announcement);
    }
}
