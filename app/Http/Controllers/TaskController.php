<?php

namespace App\Http\Controllers;

use App\Enums\BulkAction;
use App\Enums\DueFilter;
use App\Enums\TaskPriority;
use App\Enums\TaskSort;
use App\Enums\TaskStatus;
use App\Http\Requests\BulkTaskRequest;
use App\Http\Requests\ReorderTaskRequest;
use App\Http\Requests\ScheduleTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Src\TaskSorter;

class TaskController extends Controller
{
    public function index(Request $request, TaskSorter $sorter): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'due' => ['nullable', Rule::enum(DueFilter::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'sort' => ['nullable', Rule::enum(TaskSort::class)],
            // A project id, or "none" for the tasks that carry no project.
            'project' => ['nullable', ...($request->input('project') === 'none'
                ? ['in:none']
                : ['integer', Rule::exists('categories', 'id')])],
            'search' => ['nullable', 'string', 'max:100'],
            // Not beside status: "give me completed tasks, but hide completed tasks" can only ever
            // answer nothing, and an empty list is a worse reply than an error.
            'completed' => ['nullable', 'boolean', 'prohibits:status'],
        ]);

        $tasks = Task::query()
            // Without this the list would run one category query per row.
            ->with('category')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->when($filters['project'] ?? null, fn (Builder $query, string|int $project) => $project === 'none'
                ? $query->whereNull('category_id')
                : $query->where('category_id', $project))
            // "!" escapes, because it is the one escape character MySQL and SQLite both accept.
            ->when(isset($filters['search']), fn (Builder $query) => $query->whereRaw(
                "title LIKE ? ESCAPE '!'",
                ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']).'%'],
            ))
            // The Display panel's "Completed tasks" toggle. Only the off position narrows anything.
            ->when(
                isset($filters['completed']) && ! $request->boolean('completed'),
                fn (Builder $query) => $query->whereIn('status', TaskStatus::unfinished()),
            )
            ->when($filters['due'] ?? null, fn (Builder $query, string $due) => $this->applyDueFilter($query, DueFilter::from($due)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('due_date', '<=', $to))
            ->get();

        // TaskSorter works on plain arrays, so serialize through the resource first.
        $rows = TaskResource::collection($tasks)->resolve($request);
        $sort = TaskSort::tryFrom($filters['sort'] ?? '') ?? TaskSort::Default;

        return response()->json(['data' => $this->order($rows, $sort, $sorter)]);
    }

    public function show(Task $task): TaskResource
    {
        return TaskResource::make($task->load('category', 'subtasks'));
    }

    /**
     * Backs the task dialog, where the name, description, priority and project are edited.
     * The due date keeps its own endpoint, because the calendar changes it by dragging.
     */
    public function update(UpdateTaskRequest $request, Task $task): TaskResource
    {
        $data = $request->validated();

        if (array_key_exists('category_name', $data)) {
            $task->category()->associate($data['category_name'] === null ? null : Category::named($data['category_name']));
        }

        if (array_key_exists('color', $data)) {
            $task->color = $data['color'];
        }

        $task->update(Arr::except($data, ['category_name', 'color']));

        return TaskResource::make($task->load('category', 'subtasks'));
    }

    public function stats(): JsonResponse
    {
        // "Unfinished" covers every stage before Done. Starting a task is not finishing it, so
        // it must not move the number that says how much is left.
        $open = TaskStatus::unfinished();
        $openList = implode(', ', array_fill(0, count($open), '?'));
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();

        // One query with conditional counts instead of six separate COUNT queries. Each line is
        // one number on the sidebar: what makes a task count, and the values that test needs.
        // Nothing here comes from the request, and every value is a binding rather than text in
        // the SQL. Plain date comparisons, never DATE(), which would hide the column from its own
        // index and read the whole table for the two busiest badges.
        $counts = [
            'pending' => ["status IN ({$openList})", $open],
            'completed' => ['status = ?', [TaskStatus::Completed->value]],
            'high_priority_pending' => ["status IN ({$openList}) AND priority = ?", [...$open, TaskPriority::High->value]],
            'overdue' => ["status IN ({$openList}) AND due_date < ?", [...$open, $today]],
            // Unfinished only, because the Today view it labels is a work queue: finishing a task
            // drops it out of both. The badge and the rows it opens have to agree.
            'due_today' => ["status IN ({$openList}) AND due_date >= ? AND due_date < ?", [...$open, $today, $tomorrow]],
        ];

        $query = Task::query()->toBase()->selectRaw('COUNT(*) AS total');

        foreach ($counts as $name => [$test, $bindings]) {
            $query->selectRaw("SUM(CASE WHEN {$test} THEN 1 ELSE 0 END) AS {$name}", $bindings);
        }

        // SUM() returns NULL on an empty table and drivers may return numeric strings, so normalise to int.
        return response()->json(['data' => array_map('intval', (array) $query->first())]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $data = $request->validated();

        $task = DB::transaction(function () use ($data) {
            $task = new Task(Arr::except($data, 'category_name'));

            if (($data['category_name'] ?? null) !== null) {
                $task->category()->associate(Category::named($data['category_name']));
            }

            $task->save();

            return $task;
        });

        return TaskResource::make($task->load('category'))->response()->setStatusCode(201);
    }

    /**
     * Moves a task into the middle column of the board.
     *
     * Beyond the exam's four endpoints, like reopen and restore: a board with a column nothing
     * can be put into is a column that is always empty.
     */
    public function start(Task $task): TaskResource
    {
        $this->setStage($task, TaskStatus::InProgress);

        return TaskResource::make($task->load('category'));
    }

    /**
     * Moves a task into review: the work is done, but nobody has checked it yet.
     */
    public function review(Task $task): TaskResource
    {
        $this->setStage($task, TaskStatus::InReview);

        return TaskResource::make($task->load('category'));
    }

    public function complete(Task $task): TaskResource
    {
        $this->setStage($task, TaskStatus::Completed);

        return TaskResource::make($task->load('category'));
    }

    public function reopen(Task $task): TaskResource
    {
        $this->setStage($task, TaskStatus::Pending);

        return TaskResource::make($task->load('category'));
    }

    /**
     * Backs dropping a card between two others on the board, which says both which column the task
     * belongs in and where in that column someone wants it.
     *
     * The stage change goes through setStage like every other one.
     */
    public function reorder(ReorderTaskRequest $request, Task $task): TaskResource
    {
        $status = TaskStatus::from($request->validated('status'));
        $after = $request->validated('after');

        DB::transaction(function () use ($task, $status, $after) {
            if ($task->status !== $status) {
                $this->setStage($task, $status);
            }

            $this->placeAfter($task, $status, $after === null ? null : (int) $after);
        });

        return TaskResource::make($task->refresh()->load('category'));
    }

    /**
     * Backs dragging a task onto a day in the calendar, and clearing its date again.
     */
    public function schedule(ScheduleTaskRequest $request, Task $task): TaskResource
    {
        $task->due_date = $request->validated('due_date');
        $task->save();

        return TaskResource::make($task->load('category'));
    }

    public function destroy(Task $task): JsonResponse
    {
        // Soft delete: the row is stamped, not removed, so restore() can bring it back.
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * Backs the Undo action on the delete toast.
     */
    public function restore(Task $task): TaskResource
    {
        $task->restore();

        return TaskResource::make($task->load('category'));
    }

    /**
     * One action across a selection of tasks, from the table's selection toolbar.
     *
     * It is one request and one transaction rather than one request per row. Five rows completed
     * as five requests is five chances to fail half way, leaving a selection the user has to work
     * out for themselves; it also spends five of the rate limiter's allowance on one click.
     *
     * A task already in the state being asked for is skipped, not refused. Selecting five rows of
     * which two are already done and pressing Complete should finish the other three, so what comes
     * back is the ids that actually changed — which is exactly what Undo has to send to reverse it,
     * and the reason Undo cannot simply reuse what was selected.
     */
    public function bulk(BulkTaskRequest $request): JsonResponse
    {
        $action = $request->enum('action', BulkAction::class);
        $ids = $request->collect('ids')->all();

        $changed = DB::transaction(function () use ($action, $ids): array {
            $query = $action === BulkAction::Restore
                ? Task::withTrashed()->whereIn('id', $ids)
                : Task::whereIn('id', $ids);

            $changed = [];

            foreach ($query->get() as $task) {
                if (! $this->applyBulk($task, $action)) {
                    continue;
                }

                $changed[] = $task->getKey();
            }

            return $changed;
        });

        return response()->json([
            'message' => count($changed).' '.(count($changed) === 1 ? 'task' : 'tasks').' updated.',
            'count' => count($changed),
            'ids' => $changed,
            'undo' => $action->reverse()->value,
        ]);
    }

    /** Returns whether the task actually changed, which is what makes it part of the undo. */
    private function applyBulk(Task $task, BulkAction $action): bool
    {
        $status = $action->status();

        if ($status !== null) {
            if ($task->status === $status) {
                return false;
            }

            $this->setStage($task, $status);

            return true;
        }

        if ($action === BulkAction::Delete) {
            $task->delete();

            return true;
        }

        $task->restore();

        return true;
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyDueFilter(Builder $query, DueFilter $due): void
    {
        $today = today()->toDateString();
        $tomorrow = today()->addDay()->toDateString();

        // Overdue, Today and Upcoming are work queues: they answer "what is still left to do",
        // so completing a task drops it out of them and the sidebar count follows. Starting one
        // does not: a task in progress is the one its owner is most likely looking for. None backs the
        // calendar's unscheduled tray, which is somewhere to park a task rather than a queue,
        // so it keeps completed ones.
        //
        // Plain comparisons, never whereDate(): wrapping the column in DATE() hides it from its
        // own index, and MySQL then reads every row for the three busiest views in the app. "Due
        // today" becomes the day as a range instead, which is correct on both databases — the test
        // database stores a date with a 00:00:00 time on it, and that time falls inside the range.
        match ($due) {
            DueFilter::Overdue => $query->where('due_date', '<', $today)->whereIn('status', TaskStatus::unfinished()),
            DueFilter::Today => $query->where('due_date', '>=', $today)
                ->where('due_date', '<', $tomorrow)
                ->whereIn('status', TaskStatus::unfinished()),
            DueFilter::Upcoming => $query->where('due_date', '>=', $today)->whereIn('status', TaskStatus::unfinished()),
            DueFilter::None => $query->whereNull('due_date'),
        };
    }

    /**
     * Due date and Name replace TaskSorter outright; Default keeps it and splits the result by
     * stage. The stage split is here in all three, so the list never buries live work under done
     * work whichever sort is chosen.
     *
     * @param  array<int, array{status: string, title: string, due_date: ?string}>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function order(array $rows, TaskSort $sort, TaskSorter $sorter): array
    {
        $sorted = match ($sort) {
            TaskSort::Default => $sorter->sortTasks($rows),
            TaskSort::DueDate => $this->sortBy($rows, fn (array $task) => $task['due_date'] ?? '9999-12-31'),
            TaskSort::Name => $this->sortBy($rows, fn (array $task) => mb_strtolower($task['title'])),
            TaskSort::Manual => $this->sortBy($rows, fn (array $task) => $task['position']),
        };

        return $this->byStage($sorted);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortBy(array $rows, callable $key): array
    {
        // usort works on the copy PHP already made, so the caller's array is untouched.
        usort($rows, fn (array $a, array $b) => $key($a) <=> $key($b));

        return $rows;
    }

    /**
     * The one path to a stage change. start, complete, reopen, a board drop and a bulk action all
     * come through here.
     */
    private function setStage(Task $task, TaskStatus $status): void
    {
        $task->status = $status;
        $task->save();
    }

    /**
     * Puts a task straight after another one in its column, or at the top when nothing precedes
     * it, and renumbers the column from scratch.
     *
     * Renumbering rather than nudging the neighbours is what keeps the column honest: positions
     * cannot drift into ties or gaps however many times a card is dragged. The siblings move
     * through the query builder, so a renumber does not stamp every one of them as updated.
     */
    private function placeAfter(Task $task, TaskStatus $status, ?int $after): void
    {
        $ids = Task::query()
            ->where('status', $status)
            ->whereKeyNot($task->getKey())
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        // An anchor this column no longer holds — another tab moved or finished it between the
        // drag and the drop — puts the card at the end rather than losing the move.
        $index = $after === null ? -1 : array_search($after, $ids, true);
        $position = $index === false ? count($ids) : $index + 1;

        array_splice($ids, $position, 0, [$task->getKey()]);

        foreach ($ids as $place => $id) {
            DB::table('tasks')->where('id', $id)->update(['position' => $place]);
        }
    }

    /**
     * TaskSorter ranks by priority then age, which would let a finished task from last week sit
     * above today's urgent one. Splitting the sorted list keeps each group in TaskSorter's order
     * while leaving the actionable work on top.
     *
     * To do, In progress, In review, then Done: the same order the board's columns read left to
     * right, because they are the same four stages and one of them cannot run backwards. TaskSorter
     * itself never learns any of this — the split is here, so Part 1 stays exactly as the exam
     * specifies it.
     *
     * @param  array<int, array{status: string}>  $tasks
     * @return array<int, array{status: string}>
     */
    private function byStage(array $tasks): array
    {
        $inStage = fn (TaskStatus $status) => array_filter(
            $tasks,
            fn (array $task) => $task['status'] === $status->value,
        );

        return [
            ...$inStage(TaskStatus::Pending),
            ...$inStage(TaskStatus::InProgress),
            ...$inStage(TaskStatus::InReview),
            ...$inStage(TaskStatus::Completed),
        ];
    }
}
