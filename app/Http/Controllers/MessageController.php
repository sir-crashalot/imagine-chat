<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    /**
     * Display all messages (chat history).
     */
    public function index(): JsonResponse
    {
        // Fetch all messages with user information, ordered by creation time
        $messages = Message::with('user:id,username,github_avatar_url')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function ($message) {
                return [
                    'id' => $message->id,
                    'user_id' => $message->user_id,
                    'username' => $message->user->username,
                    'avatar_url' => $message->user->github_avatar_url,
                    'content' => e($message->content), // XSS protection - escape HTML
                    'created_at' => $message->created_at->toIso8601String(),
                ];
            });

        return response()->json(['messages' => $messages]);
    }

    /**
     * Store a newly created message.
     */
    public function store(StoreMessageRequest $request): JsonResponse
    {
        // Create new message
        $message = Message::create([
            'user_id' => Auth::id(),
            'content' => $request->validated()['content'],
        ]);

        // Load user relationship
        $message->load('user:id,username,github_avatar_url');

        // Return message with escaped content for XSS protection
        return response()->json([
            'message' => [
                'id' => $message->id,
                'user_id' => $message->user_id,
                'username' => $message->user->username,
                'avatar_url' => $message->user->github_avatar_url,
                'content' => e($message->content), // XSS protection - escape HTML
                'created_at' => $message->created_at->toIso8601String(),
            ],
        ], 201);
    }
}

