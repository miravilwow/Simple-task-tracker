<?php

namespace App\Http\Controllers;

use App\Enums\ActivityAction;
use App\Enums\DueFilter;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ScheduleTaskRequest;
use App\Http\Requests\StoreTaskRequest;
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
        ]);

        $tasks = Task::query()
            // Without this the list would run one category query per row.
            ->with('category')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($filters['due'] ?? null, fn (Builder $query, string $due) => $this->applyDueFilter($query, DueFilter::from($due)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('due_date', '<=', $to))
            ->get();

        // TaskSorter works on plain arrays, so serialize through the resource first.
        $rows = TaskResource::collection($tasks)->resolve($request);

        return response()->json(['data' => $this->pendingFirst($sorter->sortTasks($rows))]);
    }

    public function stats(): JsonResponse
    {
        $pending = TaskStatus::Pending->value;

        // One query with conditional counts instead of six separate COUNT queries.
        $counts = Task::query()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [$pending])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [TaskStatus::Completed->value])
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND priority = ? THEN 1 ELSE 0 END) AS high_priority_pending',
                [$pending, TaskPriority::High->value]
            )
            // DATE() because SQLite stores the cast date with a 00:00:00 time that a bare
            // comparison against 'Y-m-d' would never match.
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND DATE(due_date) < ? THEN 1 ELSE 0 END) AS overdue',
                [$pending, today()->toDateString()]
            )
            // Pending only, because the Today view it labels is a work queue: finishing a task
            // drops it out of both. The badge and the rows it opens have to agree.
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND DATE(due_date) = ? THEN 1 ELSE 0 END) AS due_today',
                [$pending, today()->toDateString()]
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
        // so completing a task drops it out of them and the sidebar count follows. None backs the
        // calendar's unscheduled tray, which is somewhere to park a task rather than a queue,
        // so it keeps completed ones.
        match ($due) {
            DueFilter::Overdue => $query->whereDate('due_date', '<', $today)->where('status', TaskStatus::Pending),
            DueFilter::Today => $query->whereDate('due_date', $today)->where('status', TaskStatus::Pending),
            DueFilter::Upcoming => $query->whereDate('due_date', '>=', $today)->where('status', TaskStatus::Pending),
            DueFilter::None => $query->whereNull('due_date'),
        };
    }

    /**
     * TaskSorter ranks by priority then age, which would let a finished task from last week sit
     * above today's urgent one. Splitting the sorted list keeps each group in TaskSorter's order
     * while leaving the actionable work on top.
     *
     * @param  array<int, array{status: string}>  $tasks
     * @return array<int, array{status: string}>
     */
    private function pendingFirst(array $tasks): array
    {
        $isPending = fn (array $task) => $task['status'] === TaskStatus::Pending->value;

        return [
            ...array_filter($tasks, $isPending),
            ...array_filter($tasks, fn (array $task) => ! $isPending($task)),
        ];
    }
}
