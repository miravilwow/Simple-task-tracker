<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dropping a card between two others on the board. TaskSort::Manual is the only order that reads
 * the result, so TaskSorter stays exactly what the exam grades.
 *
 * A drop names the task the card should follow rather than an index, because the client counts the
 * cards it drew and a filter can hide some of them.
 */
class TaskReorderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function manualOrder(string $status): array
    {
        return array_column(
            $this->getJson("/api/tasks?sort=manual&status={$status}")->assertOk()->json('data'),
            'title',
        );
    }

    /**
     * @return array<string, int>
     */
    private function column(string ...$titles): array
    {
        $made = now()->subDay();

        foreach ($titles as $index => $title) {
            Task::factory()->create([
                'title' => $title,
                'status' => 'pending',
                'position' => $index,
                'created_at' => $made,
            ]);
        }

        return Task::query()->orderBy('position')->pluck('id', 'title')->all();
    }

    private function drop(int $id, string $status, ?int $after): \Illuminate\Testing\TestResponse
    {
        return $this->patchJson("/api/tasks/{$id}/reorder", ['status' => $status, 'after' => $after]);
    }

    public function test_a_new_task_joins_the_end_of_its_column(): void
    {
        $this->column('First', 'Second');

        $this->postJson('/api/tasks', ['title' => 'Third', 'priority' => 'low', 'due_date' => today()->toDateString(), 'due_time' => '09:00'])->assertCreated();

        $this->assertSame(['First', 'Second', 'Third'], $this->manualOrder('pending'));
    }

    public function test_a_card_can_be_dropped_between_two_others(): void
    {
        $ids = $this->column('First', 'Second', 'Third');

        $this->drop($ids['Third'], 'pending', $ids['First'])
            ->assertOk()
            ->assertJsonPath('data.position', 1);

        $this->assertSame(['First', 'Third', 'Second'], $this->manualOrder('pending'));
    }

    public function test_a_card_can_be_dropped_at_the_top(): void
    {
        $ids = $this->column('First', 'Second', 'Third');

        // Nothing above the gap means the top of the column.
        $this->drop($ids['Third'], 'pending', null)->assertOk();

        $this->assertSame(['Third', 'First', 'Second'], $this->manualOrder('pending'));
    }

    public function test_a_card_can_be_dropped_at_the_bottom(): void
    {
        $ids = $this->column('First', 'Second', 'Third');

        $this->drop($ids['First'], 'pending', $ids['Third'])->assertOk();

        $this->assertSame(['Second', 'Third', 'First'], $this->manualOrder('pending'));
    }

    public function test_an_anchor_the_column_no_longer_holds_lands_the_card_at_the_end(): void
    {
        $ids = $this->column('First', 'Second');
        $elsewhere = Task::factory()->create(['title' => 'Moved away', 'status' => 'completed']);

        // Another tab finished the card this drop was aimed at. Losing the move would be worse.
        $this->drop($ids['First'], 'pending', $elsewhere->id)->assertOk();

        $this->assertSame(['Second', 'First'], $this->manualOrder('pending'));
    }

    public function test_the_column_is_renumbered_with_no_gaps_or_ties(): void
    {
        $ids = $this->column('First', 'Second', 'Third');

        $this->drop($ids['Second'], 'pending', $ids['Third'])->assertOk();

        $this->assertSame([0, 1, 2], Task::query()->orderBy('position')->pluck('position')->all());
    }

    public function test_a_drop_into_another_column_changes_the_stage_and_the_position(): void
    {
        $ids = $this->column('First', 'Second');

        $this->drop($ids['First'], 'in_progress', null)
            ->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.position', 0);

        $this->assertSame(['Second'], $this->manualOrder('pending'));
        $this->assertSame(['First'], $this->manualOrder('in_progress'));
    }

    public function test_a_drop_is_unaffected_by_what_a_filter_is_hiding(): void
    {
        $ids = $this->column('First', 'Second', 'Third');

        // The board is showing First and Third only; Second is hidden by a priority filter. An
        // index counted on screen would put the card in the wrong place, the anchor does not.
        $this->drop($ids['Third'], 'pending', $ids['First'])->assertOk();

        $this->assertSame(['First', 'Third', 'Second'], $this->manualOrder('pending'));
    }

    public function test_manual_order_leaves_the_graded_default_alone(): void
    {
        $made = now()->subDay();
        Task::factory()->create(['title' => 'Low', 'priority' => 'low', 'position' => 0, 'created_at' => $made]);
        Task::factory()->create(['title' => 'High', 'priority' => 'high', 'position' => 1, 'created_at' => $made]);

        // The exam grades this order. Manual is a sort beside it, never a replacement for it.
        $this->assertSame(['High', 'Low'], array_column($this->getJson('/api/tasks')->json('data'), 'title'));
        $this->assertSame(['Low', 'High'], $this->manualOrder('pending'));
    }

    public function test_manual_order_still_puts_unfinished_work_first(): void
    {
        Task::factory()->create(['title' => 'Done', 'status' => 'completed', 'position' => 0]);
        Task::factory()->create(['title' => 'To do', 'status' => 'pending', 'position' => 9]);

        $this->assertSame(
            ['To do', 'Done'],
            array_column($this->getJson('/api/tasks?sort=manual')->json('data'), 'title'),
        );
    }

    public function test_reorder_rejects_a_missing_after(): void
    {
        $task = Task::factory()->create();

        // Present but nullable, the same shape as schedule: omitting the key is a 400 rather than
        // a silent drop at the top of the column.
        $this->patchJson("/api/tasks/{$task->id}/reorder", ['status' => 'pending'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('after');
    }

    public function test_reorder_rejects_a_missing_status(): void
    {
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/reorder", ['after' => null])
            ->assertStatus(400)
            ->assertJsonValidationErrors('status');
    }

    public function test_reorder_rejects_an_unknown_status(): void
    {
        $task = Task::factory()->create();

        $this->drop($task->id, 'sideways', null)
            ->assertStatus(400)
            ->assertJsonValidationErrors('status');
    }

    public function test_reorder_rejects_an_anchor_that_is_not_a_task(): void
    {
        $task = Task::factory()->create();

        $this->drop($task->id, 'pending', 999)
            ->assertStatus(400)
            ->assertJsonValidationErrors('after');
    }

    public function test_reorder_returns_404_for_a_missing_task(): void
    {
        $this->drop(999, 'pending', null)->assertNotFound();
    }
}
