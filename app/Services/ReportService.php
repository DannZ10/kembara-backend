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
    /**
     * Statuses that count as realized revenue (paid / in-progress / completed).
     */
    private const REVENUE_STATUSES = [
        BookingStatus::CONFIRMED,
        BookingStatus::ACTIVE,
        BookingStatus::RETURNED,
    ];

    public function getPopularGears(int $limit = 5): Collection
    {
        return BookingItem::select('gear_id', DB::raw('SUM(quantity) as total_rented'), DB::raw('SUM(line_total) as total_revenue'))
            ->with('gear.category')
            ->groupBy('gear_id')
            ->orderByDesc('total_rented')
            ->limit($limit)
            ->get();
    }

    /**
     * Revenue grouped by period. Driver-aware so it works on both MySQL
     * (DATE_FORMAT) and SQLite (strftime, used by the test suite).
     */
    public function getRevenueReport(string $groupBy = 'daily', ?string $from = null, ?string $to = null): Collection
    {
        $format = $groupBy === 'monthly' ? '%Y-%m' : '%Y-%m-%d';
        $periodExpr = $this->periodExpression('created_at', $format);

        $query = Booking::whereIn('status', self::REVENUE_STATUSES)
            ->select(
                DB::raw("{$periodExpr} as period"),
                DB::raw('COUNT(id) as total_bookings'),
                DB::raw('SUM(total_price) as total_revenue'),
                DB::raw('SUM(delivery_fee) as total_delivery_fee')
            );

        $this->applyDateRange($query, 'created_at', $from, $to);

        return $query->groupBy('period')->orderBy('period', 'asc')->get();
    }

    public function getLowStockGears(int $threshold = 3): Collection
    {
        return Gear::with('category')
            ->where('stock_available', '<=', $threshold)
            ->orderBy('stock_available', 'asc')
            ->get();
    }

    /**
     * Busiest rental dates ("jadwal paling ramai") — how many bookings start
     * on each date, most crowded first.
     */
    public function getBusiestPeriods(int $limit = 7): Collection
    {
        return Booking::select(
            'start_date',
            DB::raw('COUNT(id) as total_bookings'),
            DB::raw('SUM(duration_days) as total_rental_days')
        )
            ->groupBy('start_date')
            ->orderByDesc('total_bookings')
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get();
    }

    /**
     * Booking count + revenue per status — chart-ready (pie/bar).
     */
    public function getStatusBreakdown(): Collection
    {
        return Booking::select(
            'status',
            DB::raw('COUNT(id) as total'),
            DB::raw('SUM(total_price) as total_value')
        )
            ->groupBy('status')
            ->get();
    }

    /**
     * Revenue + units rented per category — chart-ready (bar).
     */
    public function getCategoryPerformance(): Collection
    {
        return BookingItem::query()
            ->join('gears', 'gears.id', '=', 'booking_items.gear_id')
            ->join('gear_categories', 'gear_categories.id', '=', 'gears.category_id')
            ->select(
                'gear_categories.id as category_id',
                'gear_categories.name as category_name',
                DB::raw('SUM(booking_items.quantity) as total_rented'),
                DB::raw('SUM(booking_items.line_total) as total_revenue')
            )
            ->groupBy('gear_categories.id', 'gear_categories.name')
            ->orderByDesc('total_revenue')
            ->get();
    }

    public function getDashboardSummary(): array
    {
        $totalRevenue = Booking::whereIn('status', self::REVENUE_STATUSES)->sum('total_price');

        return [
            'total_gears' => Gear::count(),
            'total_bookings' => Booking::count(),
            'total_revenue' => (float) $totalRevenue,
            'pending_bookings' => Booking::where('status', BookingStatus::PENDING)->count(),
            'active_rentals' => Booking::where('status', BookingStatus::ACTIVE)->count(),
        ];
    }

    /**
     * Build a DB-driver-appropriate date-format expression for grouping.
     */
    private function periodExpression(string $column, string $format): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'sqlite'
            ? "strftime('{$format}', {$column})"
            : "DATE_FORMAT({$column}, '{$format}')";
    }

    private function applyDateRange($query, string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $query->whereDate($column, '<=', $to);
        }
    }
}
