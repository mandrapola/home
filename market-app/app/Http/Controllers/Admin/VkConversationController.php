<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\VkConversation;
use App\Services\Vk\VkClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VkConversationController extends Controller
{
    public function index(): View
    {
        return view('admin.vk.index', [
            'conversations' => VkConversation::query()
                ->with(['item', 'order'])
                ->withCount('messages')
                ->latest()
                ->get(),
        ]);
    }

    public function show(VkConversation $conversation): View
    {
        return view('admin.vk.show', [
            'conversation' => $conversation->load(['item', 'order', 'messages' => fn ($query) => $query->oldest()]),
        ]);
    }

    public function reply(Request $request, VkConversation $conversation, VkClient $vk): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:3000'],
        ]);

        $message = trim($validated['message']);

        if ($message === '') {
            return back()
                ->withInput()
                ->withErrors(['message' => 'Введите текст ответа.']);
        }

        $response = $vk->sendMessage($conversation->vk_user_id, $message);

        if (! isset($response['response'])) {
            return back()
                ->withInput()
                ->withErrors(['message' => 'VK не принял сообщение. Проверьте токен сообщества и права messages.']);
        }

        $conversation->messages()->create([
            'direction' => 'admin',
            'body' => $message,
            'vk_message_id' => $response['response'],
            'payload' => $response,
        ]);

        return redirect()
            ->route('admin.vk.show', $conversation)
            ->with('status', 'Ответ отправлен во VK.');
    }
}
