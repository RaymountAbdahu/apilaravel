<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\Electric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function getAllAdmin()
    {
        $pricePerKWh = 1440; // Harga per kWh dalam rupiah
        $startOfMonth = Carbon::now()->startOfMonth();
        $today = Carbon::today();

        $totalEnergyToday = 0;
        $totalEnergyThisMonth = 0;
        $roomData = [];

        // Ambil semua ruangan
        $rooms = Room::with(['electrics' => function ($query) use ($startOfMonth) {
            $query->where('created_at', '>=', $startOfMonth)->orderBy('created_at', 'desc');
        }])->get();

        foreach ($rooms as $room) {
            $electrics = $room->electrics;

            if ($electrics->isEmpty()) {
                continue;
            }

            // Ambil data electrics terakhir pada hari ini
            $lastElectricToday = $electrics->first(); // Data terakhir pada hari ini

            // Ambil data electrics terakhir sebelum hari ini (bisa lebih dari 1 hari sebelumnya)
            $previousElectric = $electrics->where('created_at', '<', $today)->first();

            $todayUsage = 0;
            if ($lastElectricToday && $previousElectric) {
                // Hitung penggunaan listrik hari ini
                $todayUsage = $lastElectricToday->energy - $previousElectric->energy;
                $totalEnergyToday += $todayUsage;
            }

            // Ambil data electric terakhir dalam ruangan untuk menghitung total energy bulan ini
            $lastElectricInRoom = $electrics->first(); // Data terakhir pada ruangan (sudah diurutkan)
            if ($lastElectricInRoom) {
                $totalEnergyThisMonth += $lastElectricInRoom->energy;
            }

            // Simpan data penggunaan untuk setiap ruangan
            $roomData[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'last_energy_today' => $lastElectricToday->energy ?? 0,
                'previous_energy' => $previousElectric->energy ?? 0,
                'today_usage' => $todayUsage,
            ];
        }

        // Hitung harga untuk penggunaan listrik
        $priceToday = $totalEnergyToday * $pricePerKWh;
        $priceThisMonth = $totalEnergyThisMonth * $pricePerKWh;

        // Format harga dalam Rupiah
        $formattedPriceToday = 'Rp ' . number_format($priceToday, 2, ',', '.');
        $formattedPriceThisMonth = 'Rp ' . number_format($priceThisMonth, 2, ',', '.');

        return response()->json([
            'total_energy_this_month' => $totalEnergyThisMonth,
            'total_energy_today' => $totalEnergyToday,
            'price_today' => $formattedPriceToday,
            'price_this_month' => $formattedPriceThisMonth,
            'room_usage' => $roomData,
        ]);
    }


    public function getAllHistory()
    {
        // Ambil semua ruangan
        $rooms = Room::all();

        $roomData = [];

        foreach ($rooms as $room) {
            // Ambil data electrics terakhir setiap hari untuk ruangan ini
            $dailyElectrics = DB::table('electrics')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('MAX(created_at) as latest_time'))
                ->where('room_id', $room->id)
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderByDesc('latest_time') // Urutkan dari data terbaru
                ->pluck('latest_time');

            $electricData = Electric::whereIn('created_at', $dailyElectrics)
                ->orderByDesc('created_at') // Urutkan data electric dari terbaru ke terlama
                ->get();

            $roomData[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'daily_electric_data' => $electricData->map(function ($electric) {
                    return [
                        'date' => $electric->created_at->format('Y-m-d'),
                        'voltage' => $electric->voltage,
                        'current' => $electric->current,
                        'power' => $electric->power,
                        'energy' => $electric->energy,
                    ];
                }),
            ];
        }

        return response()->json([
            'room_data' => $roomData,
        ]);
    }



    public function getUserHome()
    {
        $user = Auth::user();

        // Ambil semua ruangan yang dimiliki user
        $rooms = $user->rooms()->with(['electrics' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])->get();

        $totalEnergyThisMonth = 0;
        $totalEnergyToday = 0;
        $todayUsage = 0;
        $roomData = [];
        $pricePerKWh = 1440;

        foreach ($rooms as $room) {
            $electrics = $room->electrics;

            if ($electrics->isEmpty()) {
                continue;
            }

            // Ambil data electrics terakhir (hari ini) dan data sebelum hari ini
            $lastElectricToday = $electrics->first(); // Data terakhir hari ini
            $previousElectric = $electrics->where('created_at', '<', Carbon::today())->first(); // Data sebelum hari ini

            if ($lastElectricToday && $previousElectric) {
                // Hitung penggunaan listrik hari ini
                $todayUsage = $lastElectricToday->energy - $previousElectric->energy;
                $totalEnergyToday += $todayUsage; // Total penggunaan listrik hari ini seluruh ruangan
            }

            // Ambil data electric terakhir dalam ruangan untuk menghitung total energy bulan ini
            $lastElectricInRoom = $electrics->first(); // Sudah diurutkan berdasarkan created_at desc
            if ($lastElectricInRoom) {
                $totalEnergyThisMonth += $lastElectricInRoom->energy;
            }

            // Simpan data penggunaan untuk setiap ruangan
            $roomData[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'last_energy_today' => $lastElectricToday->energy ?? 0,
                'previous_energy' => $previousElectric->energy ?? 0,
                'today_usage' => $todayUsage,
            ];
        }

        // Hitung harga untuk penggunaan listrik
        $priceToday = $totalEnergyToday * $pricePerKWh;
        $priceThisMonth = $totalEnergyThisMonth * $pricePerKWh;

        // Format harga dalam Rupiah
        $formattedPriceToday = 'Rp ' . number_format($priceToday, 2, ',', '.');
        $formattedPriceThisMonth = 'Rp ' . number_format($priceThisMonth, 2, ',', '.');

        return response()->json([
            'user_id' => $user->id,
            'total_energy_this_month' => $totalEnergyThisMonth,
            'total_energy_today' => $totalEnergyToday,
            'price_today' => $formattedPriceToday,
            'price_this_month' => $formattedPriceThisMonth,
            'room_usage' => $roomData,
        ]);
    }




    public function getHistory()
    {
        $user = Auth::user();
        $rooms = $user->rooms; // Ambil semua ruangan yang dimiliki user

        $roomData = [];

        foreach ($rooms as $room) {
            // Ambil data electrics terakhir setiap hari untuk ruangan ini
            $dailyElectrics = DB::table('electrics')
                ->select(DB::raw('DATE(created_at) as date'), DB::raw('MAX(created_at) as latest_time'))
                ->where('room_id', $room->id)
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderByDesc('latest_time') // Urutkan dari data terbaru
                ->pluck('latest_time'); // Mengambil waktu terbaru tiap hari

            $electricData = Electric::whereIn('created_at', $dailyElectrics)
                ->orderByDesc('created_at') // Urutkan data electric dari terbaru ke terlama
                ->get();

            $roomData[] = [
                'room_id' => $room->id,
                'room_name' => $room->name,
                'daily_electric_data' => $electricData->map(function ($electric) {
                    return [
                        'date' => $electric->created_at->format('Y-m-d'),
                        'voltage' => $electric->voltage,
                        'current' => $electric->current,
                        'power' => $electric->power,
                        'energy' => $electric->energy,
                    ];
                }),
            ];
        }

        return response()->json([
            'user_id' => $user->id,
            'room_data' => $roomData,
        ]);
    }
}
