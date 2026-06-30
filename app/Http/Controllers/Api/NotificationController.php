<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends BaseController
{
    
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $this->sendResponse([
            'unread_count'  => $user->appNotifications()->whereNull('read_at')->count(),
            'notifications' => $user->appNotifications()->latest()->limit(50)->get(),
        ], 'Notifications retrieved successfully');
    }

  
    public function markRead($id)
    {
        $notification = Notification::where('user_id', auth()->id())->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return $this->sendResponse(null, 'Notification marked as read');
    }


    public function markAllRead()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $user->appNotifications()->whereNull('read_at')->update(['read_at' => now()]);

        return $this->sendResponse(null, 'All notifications marked as read');
    }
}