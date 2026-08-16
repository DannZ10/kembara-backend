<?php

namespace App\Services;

use App\Models\Gear;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GearService
{
    public function getPaginatedGears(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Gear::with('category');

        // Public vs Admin availability filtering
        if (isset($filters['is_available'])) {
            $query->where('is_available', filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN));
        } else {
            // Default public only sees available gears
            $query->where('is_available', true);
        }

        // Search by keyword (name, description, brand)
        if (! empty($filters['search'])) {
            $s = $filters['search'];
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('description', 'like', "%{$s}%")
                    ->orWhere('brand', 'like', "%{$s}%");
            });
        }

        // Filter by category_id or category slug
        if (! empty($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        } elseif (! empty($filters['category'])) {
            $query->whereHas('category', function ($q) use ($filters) {
                $q->where('slug', $filters['category']);
            });
        }

        // Filter by price range
        if (isset($filters['min_price'])) {
            $query->where('price_per_day', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price_per_day', '<=', $filters['max_price']);
        }

        // Filter by created_at date range
        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['price_per_day', 'created_at', 'name', 'stock_available'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, strtolower($sortOrder) === 'asc' ? 'asc' : 'desc');
        }

        return $query->paginate($perPage);
    }

    public function getGearById(int $id): Gear
    {
        return Gear::with('category')->findOrFail($id);
    }

    public function getGearBySlug(string $slug): Gear
    {
        return Gear::with('category')->where('slug', $slug)->firstOrFail();
    }

    public function createGear(array $data): Gear
    {
        // When creating, default stock_available equals stock_total if not specified
        if (! isset($data['stock_available'])) {
            $data['stock_available'] = $data['stock_total'];
        }

        return Gear::create($data);
    }

    public function updateGear(Gear $gear, array $data): Gear
    {
        $gear->update($data);
        return $gear->fresh('category');
    }

    public function deactivateGear(Gear $gear): Gear
    {
        $gear->update(['is_available' => false]);
        return $gear->fresh();
    }
}
