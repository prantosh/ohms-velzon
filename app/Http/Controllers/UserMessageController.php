<?php

namespace App\Http\Controllers;

use App\Models\UserMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserMessageController extends Controller
{
    /** Polled by the topbar bell: latest messages + unread count for the current user only. */
    public function poll()
    {
        $base = UserMessage::where('user_id', Auth::id());

        $messages = (clone $base)->orderByDesc('id')->limit(15)->get()->map(fn ($m) => [
            'id' => $m->id,
            'title' => $m->title,
            'message' => $m->message,
            'link' => $m->link,
            'unread' => $m->read_at === null,
            'time' => $m->created_at->diffForHumans(),
        ]);

        return response()->json([
            'unread' => (clone $base)->whereNull('read_at')->count(),
            'messages' => $messages,
        ]);
    }

    /** Mark one message (id) or all (no id) as read -- scoped to the current user. */
    public function markRead(Request $request)
    {
        $query = UserMessage::where('user_id', Auth::id())->whereNull('read_at');

        if ($request->filled('id')) {
            $query->where('id', $request->id);
        }

        $query->update(['read_at' => now()]);

        return response()->json(['status' => true]);
    }
}
