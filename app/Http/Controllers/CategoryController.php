<?php

namespace App\Http\Controllers;

use App\Http\Requests\MoveCategoryRequest;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    /**
     * The feed is a glance at what happened lately, not an audit trail to page through.
     */
    private const ACTIVITY_LIMIT = 50;

    /**
     * Matches the column and the max:40 rule the form requests use.
     */
    private const NAME_LIMIT = 40;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'archived' => ['nullable', 'boolean'],
        ]);

        $categories = Category::query()
            ->withCount(['tasks', 'comments'])
            // Archived projects are out of the way, not gone: the sidebar asks for them by name.
            ->when(
                $filters['archived'] ?? false,
                fn ($query) => $query->whereNotNull('archived_at'),
                fn ($query) => $query->active(),
            )
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return CategoryResource::make($this->withCounts($category))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return CategoryResource::make($this->withCounts($category));
    }

    /**
     * Backs "Move" in the project menu, and is what "move into folder" means here: a folder is
     * simply a project with children, so there is one tree rather than two.
     */
    public function move(MoveCategoryRequest $request, Category $category): CategoryResource
    {
        $category->parent_id = $request->validated('parent_id');
        $category->save();

        return CategoryResource::make($this->withCounts($category));
    }

    /**
     * Idempotent rather than a toggle: two clicks racing each other would otherwise undo one
     * another, and the menu already knows which of the two labels it is showing.
     */
    public function favorite(Request $request, Category $category): CategoryResource
    {
        $category->is_favorite = $request->validate([
            'is_favorite' => ['required', 'boolean'],
        ])['is_favorite'];
        $category->save();

        return CategoryResource::make($this->withCounts($category));
    }

    public function archive(Category $category): CategoryResource
    {
        $category->archived_at = now();
        $category->save();

        return CategoryResource::make($this->withCounts($category));
    }

    public function unarchive(Category $category): CategoryResource
    {
        $category->archived_at = null;
        $category->save();

        return CategoryResource::make($this->withCounts($category));
    }

    /**
     * Copies the project and its tasks as they are, statuses included: a duplicate that quietly
     * reopened finished work would be a different project, not a copy.
     *
     * The copy is top-level and never a favourite, because both are about where a project sits
     * rather than what it contains. Its children are not copied: duplicating a branch is a
     * different request from duplicating a project.
     */
    public function duplicate(Category $category): JsonResponse
    {
        $copy = DB::transaction(function () use ($category) {
            $copy = Category::create([
                'name' => $this->copyName($category->name),
                'description' => $category->description,
                'color' => $category->color,
                'icon' => $category->icon,
            ]);

            // replicate() rather than create(): status is deliberately not fillable, because it
            // changes only through the complete and reopen endpoints, and a copy has to keep it.
            foreach ($category->tasks()->get() as $task) {
                $copy->tasks()->save($task->replicate());
            }

            return $copy;
        });

        return CategoryResource::make($this->withCounts($copy))->response()->setStatusCode(201);
    }

    /**
     * The project's recent history, newest first.
     */
    public function activity(Category $category): AnonymousResourceCollection
    {
        $activities = $category->activities()
            // id as well as created_at, because several entries can share a second and the feed
            // would otherwise order them arbitrarily.
            ->latest('created_at')
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        return ActivityResource::collection($activities);
    }

    public function destroy(Category $category): JsonResponse
    {
        // The foreign key is nullOnDelete, so the category's tasks survive as uncategorised and
        // its children are promoted to the top level rather than disappearing with it.
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    /**
     * "Work" becomes "Work (copy)", then "Work (copy 2)". The name is unique in the database,
     * so the copy has to find a free one rather than collide.
     *
     * The base is trimmed to fit whatever suffix it ends up with, not to a fixed width: the
     * suffix grows with the count, and "(copy 100)" on a 30-character base would overflow the
     * column and fail as a database error rather than a validation one.
     */
    private function copyName(string $name): string
    {
        for ($suffix = 1;; $suffix++) {
            $tail = $suffix === 1 ? ' (copy)' : " (copy {$suffix})";
            $candidate = mb_substr($name, 0, self::NAME_LIMIT - mb_strlen($tail)).$tail;

            if (! Category::where('name', $candidate)->exists()) {
                return $candidate;
            }
        }
    }

    private function withCounts(Category $category): Category
    {
        return $category->loadCount(['tasks', 'comments']);
    }
}
