<?php

namespace App\Http\Repository;

use App\Http\Repository\Contracts\NotificationRepositoryInterface;
use App\Http\Traits\AuthUserTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\Notification;
use Exception;

class NotificationRepository implements NotificationRepositoryInterface
{
    use ResponseTrait, AuthUserTrait;

    public function index()
    {
        try {
            $notifications = $this->user()->notifications()->latest()->get();
            return $this->handleSuccessResponse("Notifications fetched successfully", $notifications);
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }

    public function destroy(string $id)
    {
        try {
            $notification = $this->user()->notifications()->findOrFail($id);
            $notification->delete(); // Soft delete as defined in model
            
            return $this->handleSuccessResponse("Notification deleted successfully");
        } catch (Exception $e) {
            return $this->handleErrorResponse($e->getMessage());
        }
    }
}
