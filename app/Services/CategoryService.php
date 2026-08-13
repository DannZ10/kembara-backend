<?php

namespace App\Services;

use App\Models\GearCategory;
use Illuminate\Database\Eloquent\Collection;

class CategoryService
{
    public function getAllCategories(): Collection
    {
        return GearCategory::withCount('gears')->get();
    }

    public function getCategoryById(int $id): GearCategory
    {
        return GearCategory::with('gears')->findOrFail($id);
    }

    public function createCategory(array $data): GearCategory
    {
        return GearCategory::create($data);
    }

    public function updateCategory(GearCategory $category, array $data): GearCategory
    {
        $category->update($data);
        return $category->fresh();
    }

    public function deleteCategory(GearCategory $category): bool
    {
        if ($category->gears()->count() > 0) {
            throw new \Exception('Kategori tidak dapat dihapus karena masih memiliki item gear.');
        }

        return $category->delete();
    }
}
