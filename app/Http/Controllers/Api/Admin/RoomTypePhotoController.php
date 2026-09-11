<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomTypePhoto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomTypePhotoController extends Controller
{
    // POST /api/admin/room-types/{type}/photos
    public function store(Request $request, string $type): JsonResponse
    {
        // Room types aren't a fixed enum - the admin can create a new one
        // just by naming it on a room. Validate against what actually
        // exists rather than a hardcoded list, so a newly-added type can
        // immediately get photos too.
        if (! Room::where('room_type', $type)->exists()) {
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
