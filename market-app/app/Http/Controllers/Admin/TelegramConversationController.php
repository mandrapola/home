<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramConversation;
use Illuminate\View\View;

class TelegramConversationController extends Controller
{
    public function index(): View
    {
        return view('admin.telegram.index', [
            'conversations' => TelegramConversation::query()
                ->with(['item', 'order'])
                ->withCount('messages')
                ->latest()
                ->get(),
        ]);
    }

    public function show(TelegramConversation $conversation): View
    {
        return view('admin.telegram.show', [
            'conversation' => $conversation->load(['item', 'order', 'messages' => fn ($query) => $query->oldest()]),
        ]);
    }
}
