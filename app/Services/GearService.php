<?php

namespace App\Services;

use App\Models\Gear;
use App\Support\Cache\CacheHelper;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class GearService
{
    private const TTL = 300;

    public function getPaginatedGears(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Gear::with('category');

        // Public vs Admin availability filtering.
        // is_available omitted  -> public: only available gears.
        // is_available=all      -> admin: every gear regardless of availability.
        // is_available=true/false -> explicit filter.
        if (! isset($filters['is_available'])) {
            $query->where('is_available', true);
        } elseif ($filters['is_available'] !== 'all') {
            $query->where('is_available', filter_var($filters['is_available'], FILTER_VALIDATE_BOOLEAN));
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

    // Cached reads return arrays, not Eloquent objects: Laravel's default
    // cache.serializable_classes=false blocks unserializing classes, so only
    // primitives round-trip safely across file/redis stores. JSON output is
    // identical to encoding the model.
    public function getGearById(int $id): array
    {
        return CacheHelper::remember(CacheHelper::CATALOG, "gears:{$id}", self::TTL, fn () => Gear::with('category')->findOrFail($id)->toArray());
    }

    public function getGearBySlug(string $slug): array
    {
        return CacheHelper::remember(CacheHelper::CATALOG, "gears:slug:{$slug}", self::TTL, fn () => Gear::with('category')->where('slug', $slug)->firstOrFail()->toArray());
    }

    public function createGear(array $data): Gear
    {
        // When creating, default stock_available equals stock_total if not specified
        if (! isset($data['stock_available'])) {
            $data['stock_available'] = $data['stock_total'];
        }

        $gear = Gear::create($data);
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $gear;
    }

    public function updateGear(Gear $gear, array $data): Gear
    {
        $gear->update($data);
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $gear->fresh('category');
    }

    public function deactivateGear(Gear $gear): Gear
    {
        $gear->update(['is_available' => false]);
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $gear->fresh();
    }
}
