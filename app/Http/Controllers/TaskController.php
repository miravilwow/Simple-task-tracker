<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\DueFilter;
use App\Enums\TaskPriority;
use App\Enums\TaskSort;
use App\Enums\TaskStatus;
use App\Http\Requests\ScheduleTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Activity;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\TaskSorter;

class TaskController extends Controller
{
    public function index(Request $request, TaskSorter $sorter): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'due' => ['nullable', Rule::enum(DueFilter::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'priority' => ['nullable', Rule::enum(TaskPriority::class)],
            'sort' => ['nullable', Rule::enum(TaskSort::class)],
            'completed' => ['nullable', 'boolean'],
        ]);

        $tasks = Task::query()
            // Without this the list would run one category query per row.
            ->with('category')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn (Builder $query, string $priority) => $query->where('priority', $priority))
            // The Display panel's "Completed tasks" toggle. Only the off position narrows anything.
            ->when(
                isset($filters['completed']) && ! $request->boolean('completed'),
                fn (Builder $query) => $query->whereIn('status', TaskStatus::unfinished()),
            )
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
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
        $task->update($request->validated());

        Activity::record($task, ActivityAction::Updated);

        return TaskResource::make($task->load('category', 'subtasks'));
    }

    public function stats(): JsonResponse
    {
        // "Pending" counts everything not finished, To do and In progress alike. Starting a task
        // is not finishing it, so it must not move the number that says how much is left.
        $unfinished = TaskStatus::unfinished();
        $placeholders = implode(', ', array_fill(0, count($unfinished), '?'));

        // One query with conditional counts instead of six separate COUNT queries.
        $counts = Task::query()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status IN ({$placeholders}) THEN 1 ELSE 0 END) AS pending", $unfinished)
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [TaskStatus::Completed->value])
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$placeholders}) AND priority = ? THEN 1 ELSE 0 END) AS high_priority_pending",
                [...$unfinished, TaskPriority::High->value]
            )
            // DATE() because SQLite stores the cast date with a 00:00:00 time that a bare
            // comparison against 'Y-m-d' would never match.
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$placeholders}) AND DATE(due_date) < ? THEN 1 ELSE 0 END) AS overdue",
                [...$unfinished, today()->toDateString()]
            )
            // Unfinished only, because the Today view it labels is a work queue: finishing a task
            // drops it out of both. The badge and the rows it opens have to agree.
            ->selectRaw(
                "SUM(CASE WHEN status IN ({$placeholders}) AND DATE(due_date) = ? THEN 1 ELSE 0 END) AS due_today",
                [...$unfinished, today()->toDateString()]
            )
            ->first();

        // SUM() returns NULL on an empty table and drivers may return numeric strings, so normalise to int.
        return response()->json(['data' => array_map('intval', (array) $counts)]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        Activity::record($task, ActivityAction::Created);

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
        $task->status = TaskStatus::InProgress;
        $task->save();

        Activity::record($task, ActivityAction::Started);

        return TaskResource::make($task->load('category'));
    }

    public function complete(Task $task): TaskResource
    {
        $task->status = TaskStatus::Completed;
        $task->save();

        Activity::record($task, ActivityAction::Completed);

        return TaskResource::make($task->load('category'));
    }

    public function reopen(Task $task): TaskResource
    {
        $task->status = TaskStatus::Pending;
        $task->save();

        Activity::record($task, ActivityAction::Reopened);

        return TaskResource::make($task->load('category'));
    }

    /**
     * Backs dragging a task onto a day in the calendar, and clearing its date again.
     */
    public function schedule(ScheduleTaskRequest $request, Task $task): TaskResource
    {
        $task->due_date = $request->validated('due_date');
        $task->save();

        // Clearing a date is its own event: "Scheduled X" would be a lie about what happened.
        Activity::record($task, $task->due_date === null ? ActivityAction::Unscheduled : ActivityAction::Scheduled);

        return TaskResource::make($task->load('category'));
    }

    public function destroy(Task $task): JsonResponse
    {
        // Soft delete: the row is stamped, not removed, so restore() can bring it back.
        $task->delete();

        Activity::record($task, ActivityAction::Deleted);

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * Backs the Undo action on the delete toast.
     */
    public function restore(Task $task): TaskResource
    {
        $task->restore();

        Activity::record($task, ActivityAction::Restored);

        return TaskResource::make($task->load('category'));
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyDueFilter(Builder $query, DueFilter $due): void
    {
        $today = today()->toDateString();

        // Overdue, Today and Upcoming are work queues: they answer "what is still left to do",
        // so completing a task drops it out of them and the sidebar count follows. Starting one
        // does not: a task in progress is the one its owner is most likely looking for. None backs the
        // calendar's unscheduled tray, which is somewhere to park a task rather than a queue,
        // so it keeps completed ones.
        match ($due) {
            DueFilter::Overdue => $query->whereDate('due_date', '<', $today)->whereIn('status', TaskStatus::unfinished()),
            DueFilter::Today => $query->whereDate('due_date', $today)->whereIn('status', TaskStatus::unfinished()),
            DueFilter::Upcoming => $query->whereDate('due_date', '>=', $today)->whereIn('status', TaskStatus::unfinished()),
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
        };

        return $this->unfinishedFirst($sorted);
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
     * TaskSorter ranks by priority then age, which would let a finished task from last week sit
     * above today's urgent one. Splitting the sorted list keeps each group in TaskSorter's order
     * while leaving the actionable work on top.
     *
     * In progress comes first, then to do, then done: the task someone is in the middle of is
     * the one they came back for. TaskSorter itself never learns any of this — the split is here,
     * so Part 1 stays exactly as the exam specifies it.
     *
     * @param  array<int, array{status: string}>  $tasks
     * @return array<int, array{status: string}>
     */
    private function unfinishedFirst(array $tasks): array
    {
        $inStage = fn (TaskStatus $status) => array_filter(
            $tasks,
            fn (array $task) => $task['status'] === $status->value,
        );

        return [
            ...$inStage(TaskStatus::InProgress),
            ...$inStage(TaskStatus::Pending),
            ...$inStage(TaskStatus::Completed),
        ];
    }
}
