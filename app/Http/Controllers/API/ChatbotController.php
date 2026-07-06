<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Services\Chat\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatbotController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Chatbot/Index');
    }

    public function ask(Request $request, ChatbotService $chatbot): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'client_id' => 'nullable|integer|exists:clients,id',
        ]);

        $client = isset($data['client_id']) ? Client::find($data['client_id']) : null;

        return response()->json($chatbot->answer($data['message'], $client));
    }
}
