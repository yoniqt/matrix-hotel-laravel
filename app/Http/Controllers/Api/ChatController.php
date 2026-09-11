<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    // The knowledge the assistant is allowed to speak from. Room
    // types/prices are pulled live from the DB so pricing never goes
    // stale; everything else mirrors the static content already shown
    // on the public site (About page, amenities section, policies).
    private function buildSystemPrompt(): string
    {
        $rooms = Room::select('room_type')
            ->selectRaw('MIN(price_per_night) as price, MAX(capacity) as capacity, COUNT(*) as room_count')
            ->groupBy('room_type')
            ->orderBy('price')
            ->get();

        $roomLines = $rooms->map(fn ($r) => "- {$r->room_type}: ₱".number_format((float) $r->price, 2)." per night, up to {$r->capacity} guests, {$r->room_count} rooms of this type in the hotel"
        )->implode("\n");

        return <<<PROMPT
            You are the friendly front-desk chat assistant for The Matrix Hotel, a luxury hotel. Answer guest questions using ONLY the information below. Keep replies short and conversational (2-4 sentences unless a list is clearer). If something isn't covered here, say you're not sure and suggest contacting the hotel directly at stay@thematrixhotel.com or +63 900 000 0000 - never invent details, prices, or policies.

            ROOM TYPES & PRICING (current, live from our system):
            {$roomLines}

            All rooms include: free Wi-Fi, air conditioning, flat-screen TV, private bathroom, daily housekeeping, hair dryer, free bath towels. Deluxe and up also include a mini bar. Suite and Family rooms have a separate living area.

            HOTEL AMENITIES:
            - Fitness Gym (2nd Floor): 8:00 AM - 5:00 PM
            - Infinity Pool (Rooftop, 15th Floor): 8:00 AM - 11:00 PM
            - Rooftop Bar (Rooftop, 16th Floor): 6:00 PM - 12:00 AM, Happy Hour 8:00 PM - 10:00 PM
            Other services: free Wi-Fi throughout, parking, and more - guests can ask front desk for the full list.

            CHECK-IN / CHECK-OUT:
            - Check-in: 2:00 PM
            - Check-out: 12:00 PM
            - Late checkout available until 3:00 PM on request, subject to availability, for a fee of 50% of the nightly rate.

            CANCELLATION POLICY:
            Free cancellation up to 48 hours before check-in. Cancellations after that are charged for one night's stay.

            BOOKING:
            Guests book directly on the website by picking dates on the homepage, choosing a room, and paying online. After booking, guests can look up or cancel a still-pending booking anytime on the "Find My Booking" page using their booking reference and email. A confirmation email is sent once payment is completed.

            LOCATION & CONTACT:
            123 Bonifacio Global City, Taguig, Metro Manila, Philippines. Phone: +63 900 000 0000. Email: stay@thematrixhotel.com.
            PROMPT;
    }

    // POST /api/chat
    public function respond(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'sometimes|array|max:12',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:1000',
        ]);

        $apiKey = config('services.groq.key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Chat assistant is not configured yet.',
            ], 503);
        }

        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt()],
            ...array_map(fn ($m) => ['role' => $m['role'], 'content' => $m['content']], $data['history'] ?? []),
            ['role' => 'user', 'content' => $data['message']],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->timeout(20)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => config('services.groq.model'),
                    'messages' => $messages,
                    'temperature' => 0.4,
                    'max_tokens' => 400,
                ]);

            if (! $response->successful()) {
                Log::error('Groq chat request failed: '.$response->body());

                return response()->json([
                    'success' => false,
                    'message' => "Sorry, I'm having trouble responding right now. Please try again in a moment.",
                ], 502);
            }

            $reply = $response->json('choices.0.message.content');

            return response()->json([
                'success' => true,
                'reply' => trim((string) $reply) ?: "Sorry, I didn't quite catch that - could you rephrase?",
            ]);
        } catch (\Throwable $e) {
            Log::error("Chat assistant error: {$e->getMessage()}");

            return response()->json([
                'success' => false,
                'message' => "Sorry, I'm having trouble responding right now. Please try again in a moment.",
            ], 502);
        }
    }
}
