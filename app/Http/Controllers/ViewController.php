<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ViewController extends Controller
{
    public function totalUsage(Request $request)
    {
        $user = Auth::user();

        $totalUsage = $user->rooms()->with('electrics')->get()->sum(function ($room) {
            return $room->electrics->sum('energy');
        });

        return response()->json(['total_usage' => $totalUsage]);
    }

    public function getData(Request $request)
    {
        $user = Auth::user();

        // Retrieve rooms along with their electrics
        $rooms = $user->rooms()->with('electrics')->get();

        return response()->json(['rooms' => $rooms]);
    }

    public function getPowerUsage(Request $request)
    {
        $user = Auth::user();

        // Get today's date and the first day of the current month
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Retrieve rooms with electrics
        $rooms = $user->rooms()->with(['electrics' => function ($query) use ($today, $startOfMonth) {
            $query->select('room_id', 'energy', 'created_at')
                ->where('created_at', '>=', $startOfMonth); // Filter by month
        }])->get();

        // Calculate today's and this month's usage
        $usageData = $rooms->map(function ($room) use ($today) {
            $todayUsage = $room->electrics->where('created_at', '>=', $today)->sum('energy');
            $monthUsage = $room->electrics->sum('energy');

            return [
                'room_name' => $room->name,
                'today_usage' => $todayUsage,
                'month_usage' => $monthUsage,
            ];
        });

        return response()->json(['usage_data' => $usageData]);
    }

    public function getAllRoomsWithUsage(Request $request)
    {
        // Get today's date and the first day of the current month
        $today = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();

        // Retrieve all rooms with their electrics
        $rooms = Room::with(['electrics' => function ($query) use ($startOfMonth) {
            $query->select('room_id', 'energy', 'created_at')
                ->where('created_at', '>=', $startOfMonth); // Filter by current month
        }])->get();

        // Calculate today's and this month's usage
        $roomsData = $rooms->map(function ($room) use ($today) {
            $todayUsage = $room->electrics->where('created_at', '>=', $today)->sum('energy');
            $monthUsage = $room->electrics->sum('energy');

            return [
                'room_name' => $room->name,
                'room_mac' => $room->mac,
                'electrics' => $room->electrics,
                'today_usage' => $todayUsage,
                'month_usage' => $monthUsage,
            ];
        });

        return response()->json(['rooms' => $roomsData]);
    }

    public function getAuthenticatedUser()
    {
        // Retrieve the authenticated user's information
        $user = Auth::user();

        // Select only the required fields
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
        ];

        return response()->json(['user' => $userData]);
    }
}
