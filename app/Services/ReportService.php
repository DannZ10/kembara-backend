<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Gear;
use App\Support\Cache\CacheHelper;
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

    /** Reports are aggregation-heavy; cache briefly, invalidate on writes. */
    private const TTL = 120;

    // Cached reads return arrays (see GearService note on serializable_classes).
    public function getPopularGears(int $limit = 5): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, "popular-gear:{$limit}", self::TTL, fn () => BookingItem::select('gear_id', DB::raw('SUM(quantity) as total_rented'), DB::raw('SUM(line_total) as total_revenue'))
            ->with('gear.category')
            ->groupBy('gear_id')
            ->orderByDesc('total_rented')
            ->limit($limit)
            ->get()
            ->toArray());
    }

    /**
     * Revenue grouped by period. Driver-aware so it works on both MySQL
     * (DATE_FORMAT) and SQLite (strftime, used by the test suite).
     */
    public function getRevenueReport(string $groupBy = 'daily', ?string $from = null, ?string $to = null): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, "revenue:{$groupBy}:{$from}:{$to}", self::TTL, function () use ($groupBy, $from, $to) {
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

            return $query->groupBy('period')->orderBy('period', 'asc')->get()->toArray();
        });
    }

    public function getLowStockGears(int $threshold = 3): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, "low-stock:{$threshold}", self::TTL, fn () => Gear::with('category')
            ->where('stock_available', '<=', $threshold)
            ->orderBy('stock_available', 'asc')
            ->get()
            ->toArray());
    }

    /**
     * Busiest rental dates ("jadwal paling ramai") — how many bookings start
     * on each date, most crowded first.
     */
    public function getBusiestPeriods(int $limit = 7): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, "busiest:{$limit}", self::TTL, fn () => Booking::select(
            'start_date',
            DB::raw('COUNT(id) as total_bookings'),
            DB::raw('SUM(duration_days) as total_rental_days')
        )
            ->groupBy('start_date')
            ->orderByDesc('total_bookings')
            ->orderBy('start_date', 'asc')
            ->limit($limit)
            ->get()
            ->toArray());
    }

    /**
     * Booking count + revenue per status — chart-ready (pie/bar).
     */
    public function getStatusBreakdown(): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, 'status-breakdown', self::TTL, fn () => Booking::select(
            'status',
            DB::raw('COUNT(id) as total'),
            DB::raw('SUM(total_price) as total_value')
        )
            ->groupBy('status')
            ->get()
            ->toArray());
    }

    /**
     * Revenue + units rented per category — chart-ready (bar).
     */
    public function getCategoryPerformance(): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, 'category-performance', self::TTL, fn () => BookingItem::query()
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
            ->get()
            ->toArray());
    }

    public function getDashboardSummary(): array
    {
        return CacheHelper::remember(CacheHelper::REPORTS, 'dashboard-summary', self::TTL, fn () => [
            'total_gears' => Gear::count(),
            'total_bookings' => Booking::count(),
            'total_revenue' => (float) Booking::whereIn('status', self::REVENUE_STATUSES)->sum('total_price'),
            'pending_bookings' => Booking::where('status', BookingStatus::PENDING)->count(),
            'active_rentals' => Booking::where('status', BookingStatus::ACTIVE)->count(),
        ]);
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
