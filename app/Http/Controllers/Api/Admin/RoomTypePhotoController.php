<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RoomTypePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomTypePhotoController extends Controller
{
    private const ROOM_TYPES = ['Standard', 'Deluxe', 'Suite', 'Family'];

    // POST /api/admin/room-types/{type}/photos
    public function store(Request $request, string $type): JsonResponse
    {
        if (! in_array($type, self::ROOM_TYPES, true)) {
            return response()->json(['success' => false, 'message' => 'Unknown room type.'], 404);
        }

        $request->validate([
            'photo' => 'required|image|max:5120',
        ]);

        $path = $request->file('photo')->store('room-photos', 'public');

        $photo = RoomTypePhoto::create([
            'room_type' => $type,
            'path' => $path,
        ]);

        return response()->json(['success' => true, 'data' => ['id' => $photo->id, 'url' => $photo->url]], 201);
    }

    // DELETE /api/admin/room-type-photos/:id
    public function destroy(int $id): JsonResponse
    {
        $photo = RoomTypePhoto::find($id);

        if (! $photo) {
            return response()->json(['success' => false, 'message' => 'Photo not found.'], 404);
        }

        Storage::disk('public')->delete($photo->path);
        $photo->delete();

        return response()->json(['success' => true, 'message' => 'Photo deleted.']);
    }
}
