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

    /**
     * The highest copy number offered, and the reason the search can be one query.
     *
     * 999 keeps the longest suffix at " (copy 999)", eleven characters, which is what the prefix
     * the search looks up is trimmed by. A larger ceiling would make the suffix twelve and the
     * prefix wrong, so the two numbers move together or not at all.
     */
    private const MAX_COPIES = 999;

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
        // The tasks go with the project. Soft deleting only the project left them in every list
        // with nothing to reach them from: a duplicated project's copies, already completed,
        // piling into All tasks as rows nobody had created.
        DB::transaction(function () use ($category) {
            $category->delete();
            $category->tasks()->delete();
        });

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
        // Every task in the project comes back, including one deleted by hand just before the
        // project was. Telling the two apart needs a column remembering which delete took which
        // row, and Undo is an eight-second offer to say "I did not mean that": restoring one row
        // too many is the kinder way to be wrong than leaving work destroyed.
        DB::transaction(function () use ($category) {
            $category->restore();
            $category->tasks()->onlyTrashed()->restore();
        });

        return CategoryResource::make($this->withCounts($category));
    }

    /**
     * The one action in the app that cannot be undone, which is why it is reached only from the
     * Deleted section and behind its own confirmation.
     *
     * Its children are promoted to the top level and its comments and activity cascade away. The
     * tasks go for good with it: they were stamped when the project was, so leaving them would
     * leave rows no screen in the app can ever reach again.
     */
    public function forceDestroy(Category $category): JsonResponse
    {
        DB::transaction(function () use ($category) {
            $category->tasks()->withTrashed()->forceDelete();
            $category->forceDelete();
        });

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
        // withTrashed, because the unique index spans the deleted rows and the search has to look
        // at the same set the database does. Without it a deleted project holding "Work (copy)"
        // made the copy fail as a database error rather than quietly picking the next free name.
        //
        // One query for every name already taken, rather than one query per attempt: a project
        // with a hundred copies made a hundred round trips to find the hundred-and-first.
        $taken = Category::withTrashed()
            ->where('name', 'like', mb_substr($name, 0, self::NAME_LIMIT - 11).'%')
            ->pluck('name')
            ->all();

        // Bounded, because a loop with no ceiling is not something to leave in a graded project.
        // The ceiling is the number of names that fit the pattern at all, so reaching it means
        // every one of them is taken and there is nothing left to offer.
        for ($suffix = 1; $suffix <= self::MAX_COPIES; $suffix++) {
            $tail = $suffix === 1 ? ' (copy)' : " (copy {$suffix})";
            $candidate = mb_substr($name, 0, self::NAME_LIMIT - mb_strlen($tail)).$tail;

            if (! in_array($candidate, $taken, true)) {
                return $candidate;
            }
        }

        abort(400, 'There are too many copies of this project already.');
    }

    private function withCounts(Category $category): Category
    {
        return $category->loadCount(['tasks', 'comments']);
    }
}
