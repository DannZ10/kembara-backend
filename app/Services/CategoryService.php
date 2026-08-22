<?php

namespace App\Services;

use App\Models\GearCategory;
use App\Support\Cache\CacheHelper;

class CategoryService
{
    private const TTL = 600;

    // Cached reads return arrays (see GearService note on serializable_classes).
    public function getAllCategories(): array
    {
        return CacheHelper::remember(CacheHelper::CATALOG, 'categories:all', self::TTL, fn () => GearCategory::withCount('gears')->get()->toArray());
    }

    public function getCategoryById(int $id): array
    {
        return CacheHelper::remember(CacheHelper::CATALOG, "categories:{$id}", self::TTL, fn () => GearCategory::with('gears')->findOrFail($id)->toArray());
    }

    public function createCategory(array $data): GearCategory
    {
        $category = GearCategory::create($data);
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $category;
    }

    public function updateCategory(GearCategory $category, array $data): GearCategory
    {
        $category->update($data);
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $category->fresh();
    }

    public function deleteCategory(GearCategory $category): bool
    {
        if ($category->gears()->count() > 0) {
            throw new \Exception('Kategori tidak dapat dihapus karena masih memiliki item gear.');
        }

        $deleted = $category->delete();
        CacheHelper::flush(CacheHelper::CATALOG, CacheHelper::REPORTS);

        return $deleted;
    }
}
