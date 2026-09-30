<?php

namespace App\Models;

use App\Enums\CategoryColor;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'color',
    ];

    protected $attributes = [
        'color' => CategoryColor::Slate->value,
    ];

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    protected function casts(): array
    {
        return [
            'color' => CategoryColor::class,
        ];
    }
}
