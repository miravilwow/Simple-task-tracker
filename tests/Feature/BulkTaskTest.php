<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Category;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One action across a selection of rows, from the table's selection toolbar.
 *
 * The endpoint is one request and one transaction rather than one request per row, so what it
 * reports back has to be exact: the ids that actually changed are what the toast's Undo sends.
 */
class BulkTaskTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, int>
     */
    private function ids(Task ...$tasks): array
    {
        return array_map(fn (Task $task) => $task->id, $tasks);
    }

    public function test_it_completes_every_selected_task(): void
    {
        $tasks = Task::factory()->count(3)->create(['status' => 'pending']);

        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => $this->ids(...$tasks)])
            ->assertOk()
            ->assertJsonPath('count', 3);

        foreach ($tasks as $task) {
            $this->assertSame('completed', $task->fresh()->status->value);
        }
    }

    public function test_it_reports_only_the_tasks_it_actually_changed(): void
    {
        // Two of the five are already finished. Completing the selection has to leave them alone
        // and report the other three, or Undo would reopen work the user never touched.
        $done = Task::factory()->count(2)->create(['status' => 'completed']);
        $todo = Task::factory()->count(3)->create(['status' => 'pending']);

        $response = $this->postJson('/api/tasks/bulk', [
            'action' => 'complete',
            'ids' => $this->ids(...$done, ...$todo),
        ])->assertOk();

        $this->assertSame(3, $response->json('count'));
        $this->assertEqualsCanonicalizing($this->ids(...$todo), $response->json('ids'));
    }

    public function test_it_names_the_action_that_undoes_it(): void
    {
        $task = Task::factory()->create(['status' => 'pending']);

        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => [$task->id]])
            ->assertOk()
            ->assertJsonPath('undo', 'reopen');

        $this->postJson('/api/tasks/bulk', ['action' => 'delete', 'ids' => [$task->id]])
            ->assertOk()
            ->assertJsonPath('undo', 'restore');
    }

    public function test_it_soft_deletes_the_selection_and_restores_it(): void
    {
        $tasks = Task::factory()->count(3)->create();
        $ids = $this->ids(...$tasks);

        $this->postJson('/api/tasks/bulk', ['action' => 'delete', 'ids' => $ids])
            ->assertOk()
            ->assertJsonPath('count', 3);

        $this->assertSame(0, Task::query()->count());
        $this->assertSame(3, Task::withTrashed()->count());

        $this->postJson('/api/tasks/bulk', ['action' => 'restore', 'ids' => $ids])
            ->assertOk()
            ->assertJsonPath('count', 3);

        $this->assertSame(3, Task::query()->count());
    }

    public function test_every_stage_change_reaches_the_activity_log(): void
    {
        // The whole reason the endpoint loops rather than running one UPDATE: a bulk change is
        // still a stage change, and the feed cannot be the one place that misses three of them.
        $category = Category::factory()->create();
        $tasks = Task::factory()->count(3)->create([
            'category_id' => $category->id,
            'status' => 'pending',
        ]);

        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => $this->ids(...$tasks)])
            ->assertOk();

        $this->assertSame(3, Activity::query()->where('action', 'completed')->count());
    }

    public function test_a_failure_part_way_leaves_nothing_changed(): void
    {
        // The transaction is the reason this is one request. A selection half applied is a state
        // the user has to work out for themselves, which is exactly what five requests risk.
        $tasks = Task::factory()->count(3)->create(['status' => 'pending']);
        $ids = $this->ids(...$tasks);
        $ids[] = $tasks[0]->id + 9999;

        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => $ids])
            ->assertStatus(400);

        foreach ($tasks as $task) {
            $this->assertSame('pending', $task->fresh()->status->value);
        }
    }

    public function test_it_refuses_a_deleted_task_for_every_action_but_restore(): void
    {
        $task = Task::factory()->create();
        $task->delete();

        // The error names the row that was wrong, not just the selection, so a client with a stale
        // list can say which one it was.
        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => [$task->id]])
            ->assertStatus(400)
            ->assertJsonStructure(['message', 'errors' => ['ids.0']]);

        $this->postJson('/api/tasks/bulk', ['action' => 'restore', 'ids' => [$task->id]])
            ->assertOk();
    }

    public function test_it_refuses_a_live_task_for_restore(): void
    {
        $task = Task::factory()->create();

        $this->postJson('/api/tasks/bulk', ['action' => 'restore', 'ids' => [$task->id]])
            ->assertStatus(400);
    }

    public function test_it_refuses_an_unknown_action(): void
    {
        $task = Task::factory()->create();

        $this->postJson('/api/tasks/bulk', ['action' => 'archive', 'ids' => [$task->id]])
            ->assertStatus(400);
    }

    public function test_it_refuses_an_empty_selection(): void
    {
        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => []])
            ->assertStatus(400);
    }

    public function test_it_refuses_a_selection_beyond_the_cap(): void
    {
        $task = Task::factory()->create();

        $this->postJson('/api/tasks/bulk', [
            'action' => 'complete',
            'ids' => array_fill(0, 101, $task->id),
        ])->assertStatus(400);
    }

    public function test_it_refuses_the_same_task_twice(): void
    {
        $task = Task::factory()->create();

        $this->postJson('/api/tasks/bulk', ['action' => 'complete', 'ids' => [$task->id, $task->id]])
            ->assertStatus(400);
    }

    public function test_reopening_a_selection_puts_it_back_to_pending(): void
    {
        $tasks = Task::factory()->count(2)->create(['status' => 'completed']);

        $this->postJson('/api/tasks/bulk', ['action' => 'reopen', 'ids' => $this->ids(...$tasks)])
            ->assertOk()
            ->assertJsonPath('count', 2);

        foreach ($tasks as $task) {
            $this->assertSame('pending', $task->fresh()->status->value);
        }
    }
}
