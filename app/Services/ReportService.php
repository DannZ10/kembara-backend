<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Gear;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function getPopularGears(int $limit = 5): Collection
    {
        return BookingItem::select('gear_id', DB::raw('SUM(quantity) as total_rented'), DB::raw('SUM(line_total) as total_revenue'))
            ->with('gear.category')
            ->groupBy('gear_id')
            ->orderByDesc('total_rented')
            ->limit($limit)
            ->get();
    }

    public function getRevenueReport(string $groupBy = 'daily'): Collection
    {
        $dateFormat = $groupBy === 'monthly' ? '%Y-%m' : '%Y-%m-%d';

        return Booking::whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::ACTIVE, BookingStatus::RETURNED])
            ->select(
                DB::raw("strftime('{$dateFormat}', created_at) as period"),
                DB::raw('COUNT(id) as total_bookings'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(delivery_fee) as total_delivery_fee')
            )
            ->groupBy('period')
            ->orderBy('period', 'asc')
            ->get();
    }

    public function getLowStockGears(int $threshold = 3): Collection
    {
        return Gear::with('category')
            ->where('stock_available', '<=', $threshold)
            ->orderBy('stock_available', 'asc')
            ->get();
    }

    public function getDashboardSummary(): array
    {
        $totalGears = Gear::count();
        $totalBookings = Booking::count();
        $totalRevenue = Booking::whereIn('status', [BookingStatus::CONFIRMED, BookingStatus::ACTIVE, BookingStatus::RETURNED])
            ->sum('total_price');
        $pendingBookings = Booking::where('status', BookingStatus::PENDING)->count();
        $activeRentals = Booking::where('status', BookingStatus::ACTIVE)->count();

        return [
            'total_gears' => $totalGears,
            'total_bookings' => $totalBookings,
            'total_revenue' => (float) $totalRevenue,
            'pending_bookings' => $pendingBookings,
            'active_rentals' => $activeRentals,
        ];
    }
}
