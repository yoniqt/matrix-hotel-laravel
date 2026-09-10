<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoomController extends Controller
{
    private const STATUSES = ['Available', 'Occupied', 'Maintenance'];

    private function validated(Request $request): array
    {
        return $request->validate([
            'room_number' => 'required|string',
            'room_type' => 'required|string',
            'price_per_night' => 'required|numeric',
            'capacity' => 'required|integer|min:1',
            'status' => 'sometimes|in:'.implode(',', self::STATUSES),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $room = Room::create($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => 'A room with that number already exists.'], 409);
            }
            throw $e;
        }

        return response()->json(['success' => true, 'data' => ['id' => $room->id]], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $this->validated($request);

        try {
            $updated = Room::where('id', $id)->update($data);
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return response()->json(['success' => false, 'message' => 'A room with that number already exists.'], 409);
            }
            throw $e;
        }

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Room not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Room updated.']);
    }

    // PATCH /api/admin/rooms/:id/status - quick housekeeping toggle,
    // separate from the full edit form.
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', self::STATUSES),
        ]);

        $updated = Room::where('id', $id)->update($data);

        if ($updated === 0) {
            return response()->json(['success' => false, 'message' => 'Room not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Room status updated.']);
    }

    // rooms.id has ON DELETE CASCADE from bookings, so a plain delete
    // would silently wipe the room's booking history instead of failing -
    // block it explicitly if any booking still references this room.
    public function destroy(int $id): JsonResponse
    {
        $hasBookings = Booking::where('room_id', $id)->exists();

        if ($hasBookings) {
            return response()->json(['success' => false, 'message' => "This room has existing bookings and can't be deleted."], 409);
        }

        $deleted = Room::destroy($id);

        if ($deleted === 0) {
            return response()->json(['success' => false, 'message' => 'Room not found.'], 404);
        }

        return response()->json(['success' => true, 'message' => 'Room deleted.']);
    }
}
