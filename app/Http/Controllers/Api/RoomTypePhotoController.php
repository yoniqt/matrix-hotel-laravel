<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoomTypePhoto;
use Illuminate\Http\JsonResponse;

class RoomTypePhotoController extends Controller
{
    // GET /api/room-types/photos - grouped by room_type, e.g.
    // {"Standard": [{"id":1,"url":"..."}], "Deluxe": [], ...}. A type with
    // no uploaded photos comes back as an empty array, which the frontend
    // treats as "fall back to the bundled default gallery for this type."
    public function index(): JsonResponse
    {
        $grouped = RoomTypePhoto::orderBy('created_at')
            ->get()
            ->groupBy('room_type')
            ->map(fn ($photos) => $photos->map(fn ($p) => ['id' => $p->id, 'url' => $p->url])->values());

        return response()->json(['success' => true, 'data' => $grouped]);
    }
}
