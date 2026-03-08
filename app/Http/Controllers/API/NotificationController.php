<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Repository\Contracts\NotificationRepositoryInterface;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(protected NotificationRepositoryInterface $notificationRepository)
    {}

    /**
     * List user notifications.
     */
    public function index()
    {
        return $this->notificationRepository->index();
    }

    /**
     * Delete (soft-delete) a notification.
     */
    public function destroy(string $id)
    {
        return $this->notificationRepository->destroy($id);
    }
}
