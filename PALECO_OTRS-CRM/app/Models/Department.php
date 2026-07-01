<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;

#[Fillable(['dept_name', 'dept_desc'])]
class Department extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'dept_name' => 'string',
            'dept_desc' => 'string',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'department_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        $term = "%$term%";
    
        return $query->where(function ($query) use ($term) {
            $query->where('dept_name', 'like', $term)
                  ->orWhere('dept_desc', 'like', $term);
        });
    }

    public function scopeSort(Builder $query, ?string $sort): Builder
    {
        if (empty($sort)) {
            return $query;
        }

        switch ($sort) {
            case 'newest':
                return $query->orderBy('created_at', 'desc');
            case 'oldest':
                return $query->orderBy('created_at', 'asc');   
            case 'dept_nameASC':
                return $query->orderBy('dept_name', 'asc');
            case 'dept_nameDESC':
                return $query->orderBy('dept_name', 'desc');
            case 'dept_descASC':
                return $query->orderBy('dept_desc', 'asc');
            case 'dept_descDESC':
                return $query->orderBy('dept_desc', 'desc');
            default:
                return $query;
        }
    }
}
