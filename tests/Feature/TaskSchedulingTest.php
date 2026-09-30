<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Every "today" assertion below needs a date that will not drift mid-run.
        Carbon::setTestNow('2026-09-30 09:00:00');
    }

    public function test_creates_a_task_with_a_category_and_due_date(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/tasks', [
            'title' => 'Submit thesis outline',
            'priority' => 'high',
            'category_id' => $category->id,
            'due_date' => '2026-10-05',
        ])
            ->assertCreated()
            ->assertJsonPath('data.due_date', '2026-10-05')
            ->assertJsonPath('data.category.name', $category->name);
    }

    public function test_rejects_a_due_date_that_is_not_iso_formatted(): void
    {
        $this->postJson('/api/tasks', [
            'title' => 'Bad date',
            'priority' => 'low',
            'due_date' => '05/10/2026',
        ])
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_date');
    }

    public function test_rejects_an_unknown_category(): void
    {
        $this->postJson('/api/tasks', ['title' => 'Orphan', 'priority' => 'low', 'category_id' => 999])
            ->assertStatus(400)
            ->assertJsonValidationErrors('category_id');
    }

    public function test_schedules_a_task_onto_a_new_date(): void
    {
        $task = Task::factory()->dueOn('2026-10-01')->create();

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => '2026-10-08'])
            ->assertOk()
            ->assertJsonPath('data.due_date', '2026-10-08');

        $this->assertSame('2026-10-08', $task->fresh()->due_date->toDateString());
    }

    public function test_clears_a_due_date_with_null(): void
    {
        $task = Task::factory()->dueOn('2026-10-01')->create();

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => null])
            ->assertOk()
            ->assertJsonPath('data.due_date', null);

        $this->assertNull($task->fresh()->due_date);
    }

    public function test_schedule_requires_the_due_date_key(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/schedule", [])
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_date');
    }

    public function test_schedule_returns_404_for_missing_task(): void
    {
        $this->patchJson('/api/tasks/999/schedule', ['due_date' => '2026-10-08'])
            ->assertNotFound()
            ->assertExactJson(['message' => 'Task not found.']);
    }

    public function test_flags_a_pending_task_past_its_due_date_as_overdue(): void
    {
        Task::factory()->dueOn('2026-09-29')->create();

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.is_overdue', true);
    }

    public function test_does_not_flag_a_completed_task_as_overdue(): void
    {
        Task::factory()->completed()->dueOn('2026-09-29')->create();

        $this->getJson('/api/tasks')
            ->assertOk()
            ->assertJsonPath('data.0.is_overdue', false);
    }

    public function test_filters_by_category(): void
    {
        $work = Category::factory()->create();
        $mine = Task::factory()->create(['category_id' => $work->id]);
        Task::factory()->create();

        $response = $this->getJson("/api/tasks?category_id={$work->id}");

        $response->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($mine->id, $response->json('data.0.id'));
    }

    /**
     * @return array<string, array{string, array<int, string>}>
     */
    public static function dueFilters(): array
    {
        return [
            'overdue' => ['overdue', ['yesterday']],
            'today' => ['today', ['today']],
            'upcoming' => ['upcoming', ['today', 'tomorrow']],
            'none' => ['none', ['undated']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('dueFilters')]
    public function test_filters_by_due_window(string $filter, array $expected): void
    {
        $titles = [
            'yesterday' => Task::factory()->dueOn('2026-09-29')->create(['title' => 'yesterday']),
            'today' => Task::factory()->dueOn('2026-09-30')->create(['title' => 'today']),
            'tomorrow' => Task::factory()->dueOn('2026-10-01')->create(['title' => 'tomorrow']),
        ];
        Task::factory()->create(['title' => 'undated']);

        $response = $this->getJson("/api/tasks?due={$filter}");

        $response->assertOk();
        $this->assertEqualsCanonicalizing($expected, array_column($response->json('data'), 'title'));
        $this->assertNotEmpty($titles);
    }

    public function test_completing_a_task_drops_it_out_of_the_today_view(): void
    {
        $task = Task::factory()->dueOn('2026-09-30')->create();

        $this->getJson('/api/tasks?due=today')->assertOk()->assertJsonCount(1, 'data');

        $this->patchJson("/api/tasks/{$task->id}/complete")->assertOk();

        $this->getJson('/api/tasks?due=today')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/tasks/stats')->assertOk()->assertJsonPath('data.due_today', 0);
    }

    public function test_upcoming_filter_ignores_completed_tasks(): void
    {
        Task::factory()->completed()->dueOn('2026-10-01')->create();

        $this->getJson('/api/tasks?due=upcoming')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_the_unscheduled_tray_still_holds_completed_tasks(): void
    {
        // due=none backs the calendar tray, which is a parking spot and not a queue.
        Task::factory()->completed()->create(['due_date' => null]);

        $this->getJson('/api/tasks?due=none')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_overdue_filter_ignores_completed_tasks(): void
    {
        Task::factory()->completed()->dueOn('2026-09-29')->create();

        $this->getJson('/api/tasks?due=overdue')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filters_by_date_range_for_the_calendar(): void
    {
        Task::factory()->dueOn('2026-09-28')->create(['title' => 'before']);
        Task::factory()->dueOn('2026-10-02')->create(['title' => 'inside']);
        Task::factory()->dueOn('2026-10-20')->create(['title' => 'after']);

        $response = $this->getJson('/api/tasks?from=2026-10-01&to=2026-10-10');

        $response->assertOk();
        $this->assertSame(['inside'], array_column($response->json('data'), 'title'));
    }

    public function test_rejects_a_range_that_ends_before_it_starts(): void
    {
        $this->getJson('/api/tasks?from=2026-10-10&to=2026-10-01')
            ->assertStatus(400)
            ->assertJsonValidationErrors('to');
    }

    public function test_statistics_include_overdue_and_due_today(): void
    {
        Task::factory()->dueOn('2026-09-28')->create();
        Task::factory()->dueOn('2026-09-29')->create();
        Task::factory()->dueOn('2026-09-30')->create();
        Task::factory()->completed()->dueOn('2026-09-28')->create();

        $this->getJson('/api/tasks/stats')
            ->assertOk()
            // A completed task is never overdue, so the finished 09-28 one is left out.
            ->assertJsonPath('data.overdue', 2)
            ->assertJsonPath('data.due_today', 1);
    }

    public function test_due_today_count_matches_the_today_view(): void
    {
        Task::factory()->dueOn('2026-09-30')->create();
        Task::factory()->completed()->dueOn('2026-09-30')->create();
        Task::factory()->dueOn('2026-10-01')->create();

        $rows = $this->getJson('/api/tasks?due=today')->assertOk()->json('data');

        // The sidebar shows this count beside the view, so the two must agree.
        $this->getJson('/api/tasks/stats')
            ->assertOk()
            ->assertJsonPath('data.due_today', count($rows));

        // Only the pending one: the completed task due today has left the queue.
        $this->assertCount(1, $rows);
    }

    public function test_listing_tasks_runs_a_fixed_number_of_queries(): void
    {
        $categories = Category::factory()->count(3)->create();
        foreach ($categories as $category) {
            Task::factory()->count(3)->create(['category_id' => $category->id]);
        }

        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson('/api/tasks')->assertOk();

        // One query for the tasks, one for the eager-loaded categories: no N+1.
        $this->assertSame(2, $queries);
    }
}
