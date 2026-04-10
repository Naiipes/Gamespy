<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // Marks all unread wishlist notifications as read for the authenticated user.
    public function markAllRead(Request $request)
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Batch update unread rows in one query for the current user.
        $markedCount = $request->user()
            ->wishlistNotifications()
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json([
            'message' => 'Notifications marked as read.',
            'marked_count' => $markedCount,
        ]);
    }
}
