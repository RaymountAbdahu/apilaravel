<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class ViewController extends Controller
{
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

    public function getHome()
    {
        $user = Auth::user();
        $pricePerKwh = 1444.70; // Harga per kWh
        $today = Carbon::today(); // Get Hari Ini
        $startOfMonth = Carbon::now()->startOfMonth(); // Get Awal Bulan Ini

        // Get rooms dengan electric yang digunakan hari ini
        $rooms = $user->rooms()->with(['electrics' => function($query) use ($today) {
            $query->whereDate('updated_at', $today);
        }])->get();

        // Inisialisasi Awal
        $totalUsageToday = 0;
        $totalUsageMonth = 0;

        // Prepare the data to return
        $electricsData = $rooms->map(function ($room) use (&$totalUsageToday, &$totalUsageMonth, $startOfMonth) {
            $roomElectrics = $room->electrics;

            // Sum the energy usage for each electric device in this room that was updated today
            $roomUsageToday = $roomElectrics->sum('energy');
            $totalUsageToday += $roomUsageToday;

            // Calculate the total energy usage for the current month
            $roomUsageMonth = $room->electrics()->where('updated_at', '>=', $startOfMonth)->sum('energy');
            $totalUsageMonth += $roomUsageMonth;

            return [
                'room_name' => $room->name,
                'room_mac' => $room->mac,
                'electrics' => $roomElectrics->map(function ($electric) {
                    return [
                        'device_name' => $electric->name_device,
                        'voltage' => $electric->voltage,
                        'current' => $electric->current,
                        'power' => $electric->power,
                        'energy' => number_format($electric->energy, 2, ',', '.'), // Format energy usage
                        'updated_at' => $electric->updated_at,
                    ];
                }),
                'dayaruangan_hari' => number_format($roomUsageToday, 2, ',', '.'), // Format room usage today
                'dayaruangan_bulan' => number_format($roomUsageMonth, 2, ',', '.'), // Format room usage month
            ];
        });

        // Calculate total cost for today and this month
        $totalCostToday = $totalUsageToday * $pricePerKwh;
        $totalCostMonth = $totalUsageMonth * $pricePerKwh;

        // Format the cost and total usage to 2 decimal places and add the currency symbol for Rupiah
        $formattedTotalUsageToday = number_format($totalUsageToday, 2, ',', '.');
        $formattedTotalUsageMonth = number_format($totalUsageMonth, 2, ',', '.');
        $formattedCostToday = 'Rp ' . number_format($totalCostToday, 2, ',', '.');
        $formattedCostMonth = 'Rp ' . number_format($totalCostMonth, 2, ',', '.');

        return response()->json([
            'electrics' => $electricsData,
            'totalDaya_hari' => $formattedTotalUsageToday,
            'totatlHarga_hari' => $formattedCostToday,
            'totalDaya_bulan' => $formattedTotalUsageMonth,
            'totalHarga_bulan' => $formattedCostMonth
        ]);
    }

    public function getDevice(Request $request)
    {
        $user = Auth::user();
        $today = Carbon::today(); // Get today's date

        // Retrieve rooms along with their electrics that were updated today
        $rooms = $user->rooms()->with(['electrics' => function($query) use ($today) {
            $query->whereDate('updated_at', $today);
        }])->get();

        return response()->json(['rooms' => $rooms]);
    }
}
