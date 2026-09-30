<?php

namespace App\Models;

use App\Enums\CategoryColor;
use App\Enums\CategoryIcon;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory;

    // Deleting a project would otherwise throw away its tasks' link, its comments, its activity
    // and its children all at once, with no way back. The row is stamped so Undo can reach it.
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'color',
        'icon',
        'is_favorite',
        'parent_id',
    ];

    protected $attributes = [
        'icon' => CategoryIcon::Folder->value,
        'color' => CategoryColor::Slate->value,
        // Declared here as well as in the migration, or a freshly created model reports null for
        // it until the row is read back, and the resource would send null where the UI wants false.
        'is_favorite' => false,
    ];

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return HasMany<Activity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * @return HasMany<CategoryComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(CategoryComment::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    /**
     * Every project at or below this one, itself included.
     *
     * Used to stop a project being moved under its own child, which would cut the branch off
     * the tree entirely: it would still have a parent, but nothing reachable from the top.
     *
     * @return array<int, int>
     */
    public function descendantIds(): array
    {
        $ids = [$this->id];

        foreach ($this->children as $child) {
            $ids = [...$ids, ...$child->descendantIds()];
        }

        return $ids;
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    protected function casts(): array
    {
        return [
            'icon' => CategoryIcon::class,
            'color' => CategoryColor::class,
            'is_favorite' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }
}
