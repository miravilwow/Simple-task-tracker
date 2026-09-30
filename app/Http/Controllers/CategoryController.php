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
            'deleted' => ['nullable', 'boolean'],
        ]);

        $categories = Category::query()
            ->withCount(['tasks', 'comments'])
            // A deleted project is recoverable, so the sidebar shows it in its own section and
            // asks for it by name. Without this the row would be unreachable once the undo
            // toast had gone, which is the whole reason a soft delete is worth having.
            ->when($filters['deleted'] ?? false, fn ($query) => $query->onlyTrashed())
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
        // Soft delete: the row is stamped, not removed, so restore() can bring it back. Because
        // nothing is removed, the foreign keys never fire either: the project's tasks keep their
        // category_id and its children keep their parent_id while the row is hidden, which is
        // what lets a restore put everything back exactly where it was.
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }

    /**
     * Backs the Undo action on the delete toast.
     *
     * A deleted project keeps its name reserved: the unique index spans the stamped rows too.
     * That is the cost of the guarantee that Undo always works, and it is the cheaper half of
     * the trade — a name held while a project is recoverable, rather than a restore that can
     * fail because something else took the name in the meantime.
     */
    public function restore(Category $category): CategoryResource
    {
        $category->restore();

        return CategoryResource::make($this->withCounts($category));
    }

    /**
     * The one action in the app that cannot be undone, which is why it is reached only from the
     * Deleted section and behind its own confirmation.
     *
     * This is where the foreign keys finally fire: the project's tasks lose their category_id,
     * its children are promoted to the top level, and its comments and activity cascade away.
     */
    public function forceDestroy(Category $category): JsonResponse
    {
        $category->forceDelete();

        return response()->json(['message' => 'Category deleted permanently.']);
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
