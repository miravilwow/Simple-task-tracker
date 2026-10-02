<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tracker page renders its own controls. A Blade typo only shows up when someone opens the
 * page, which is the one check nothing else in the suite performs.
 */
class TrackerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_display_panel_holds_only_what_the_board_uses(): void
    {
        $response = $this->get('/tasks');

        $response->assertOk()->assertSeeInOrder([
            'id="board-controls"',
            'id="display-trigger"',
            'id="display-panel"',
            'id="show-completed"',
            'id="grouping"',
            'id="filter-date"',
            'id="filter-priority"',
            'id="display-reset"',
            'id="new-task-trigger"',
        ], false);
    }

    public function test_the_filter_bar_offers_search_priority_and_project(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="display-summary"',
            'id="filter-bar"',
            'id="filter-search"',
            'id="filter-bar-priority"',
            'id="filter-bar-project"',
            'id="filter-clear"',
            'id="list-view"',
        ], false);
    }

    public function test_the_display_panel_offers_no_layout_sorting_or_none_grouping(): void
    {
        // Every view decides its own layout and the board's order is the one you drag, so none of
        // these could ever change anything.
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertStringNotContainsString('data-mode=', $html);
        $this->assertStringNotContainsString('id="sorting"', $html);
        $this->assertStringNotContainsString('<option value="none">None</option>', $html);
    }

    public function test_the_sidebar_has_no_project_tree_favorites_or_project_dialogs(): void
    {
        $this->get('/tasks')
            ->assertOk()
            ->assertDontSee('My projects')
            ->assertDontSee('Favorites')
            ->assertDontSee('id="project-dialog"', false)
            ->assertDontSee('id="project-panel"', false)
            ->assertDontSee('id="move-dialog"', false);
    }

    public function test_the_board_columns_stretch_so_a_drop_lands_anywhere_in_a_column(): void
    {
        // items-start sizes each column to its own cards, which leaves the space under the last
        // card outside the column and silently refuses a drop there.
        $this->get('/tasks')
            ->assertOk()
            ->assertDontSee('md:items-start', false);
    }
    public function test_the_first_paint_is_the_boards_skeleton_not_the_list(): void
    {
        $this->get('/tasks')
            ->assertOk()
            ->assertSee('id="board-skeleton"', false)
            ->assertSee('id="list-view" class="mt-6 hidden"', false)
            ->assertDontSee('id="board-view" class="mt-6 hidden', false);
    }

    public function test_the_tracker_renders_the_three_layout_containers(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="list-view"',
            'id="board-view"',
            'id="calendar-view"',
        ], false);
    }

    public function test_the_tracker_renders_the_task_dialog(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="task-detail"',
            'id="detail-title"',
            'id="detail-description"',
            'id="subtask-list"',
            'id="subtask-form"',
            'id="detail-category-name"',
            'id="detail-date"',
            'id="detail-status"',
            'id="detail-priority"',
        ], false);
    }

    public function test_the_project_field_is_a_typed_input_over_one_datalist(): void
    {
        $page = $this->get('/tasks')->assertOk();

        $page->assertSee('id="category_name"', false)
            ->assertSee('list="project-options"', false)
            ->assertDontSee('id="category_id"', false);

        $this->assertSame(1, substr_count($page->getContent(), '<datalist id="project-options">'));
    }

    public function test_the_dialog_is_the_way_to_a_stage_outside_the_board(): void
    {
        // The Start button is gone from the rows: the board's drag moves a task into In progress,
        // and this field does it everywhere else.
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertStringContainsString('id="detail-status"', $html);
        $this->assertStringContainsString('<option value="in_progress">In progress</option>', $html);
        $this->assertStringContainsString('<option value="in_review">In review</option>', $html);
    }

    public function test_the_old_status_filter_is_gone(): void
    {
        // The Display panel carries it now, and two controls for one thing drift apart.
        $this->get('/tasks')->assertOk()->assertDontSee('data-filter=', false);
    }

    public function test_the_stat_tiles_and_the_progress_meter_are_gone(): void
    {
        // Three of the four tiles printed the same number as a sidebar badge, and the meter read
        // the whole database however the page was filtered, so it showed a ratio on a view with
        // nothing in it. The sidebar badges carry the counts now.
        $this->get('/tasks')->assertOk()
            ->assertDontSee('id="stat-total"', false)
            ->assertDontSee('id="stat-overdue"', false)
            ->assertDontSee('id="progress-bar"', false);
    }

    public function test_every_sidebar_count_is_a_figure_the_stats_endpoint_returns(): void
    {
        // The progress meter was the other reader of /api/tasks/stats. With it gone the badges
        // are the only ones left, so a count renamed on either side has to fail here rather than
        // quietly render blank.
        Task::factory()->create(['due_date' => today()]);
        Task::factory()->completed()->create();
        Task::factory()->create(['due_date' => today()->subWeek()]);

        $stats = $this->getJson('/api/tasks/stats')->assertOk()->json('data');

        preg_match_all(
            "/'count' => '([a-z_]+)'/",
            file_get_contents(resource_path('views/tasks/index.blade.php')),
            $matches
        );

        $this->assertNotEmpty($matches[1], 'The sidebar asks for no counts at all');

        foreach ($matches[1] as $key) {
            $this->assertArrayHasKey($key, $stats, "The sidebar shows \"{$key}\", which stats does not return");
        }

        $this->assertSame(3, $stats['total']);
        $this->assertSame(1, $stats['completed']);
        $this->assertSame(1, $stats['due_today']);
        $this->assertSame(1, $stats['overdue']);
    }

    public function test_no_row_offers_reopen(): void
    {
        // Reopening is the Undo on the complete toast, the dialog's Status field, or dragging a
        // card back. The row has one stage button, and it is Complete.
        $this->assertStringNotContainsString("actionLabel('Reopen')", file_get_contents(resource_path('js/app.js')));
    }

    public function test_the_table_header_carries_the_select_all_and_the_sort_buttons(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="column-headers"',
            'id="select-all"',
            'data-sort="name"',
            'data-sort="default"',
        ], false);
    }

    public function test_the_column_header_row_is_not_hidden_from_a_screen_reader(): void
    {
        // It used to be aria-hidden, which was fair when it held four decorative words. It now
        // holds the select-all checkbox and two real buttons, and hiding the row would hide them.
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertStringNotContainsString('id="column-headers" aria-hidden', $html);
    }

    public function test_status_offers_no_sort_button(): void
    {
        // Every order runs through byStage() already, so To do always precedes In progress and
        // Done. A sort button there would be a control that changes nothing.
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="table-sort"'));
        $this->assertStringNotContainsString('data-sort="status"', $html);
    }

    public function test_the_selection_toolbar_offers_complete_delete_and_clear(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="selection-bar"',
            'id="selection-count"',
            'id="selection-complete"',
            'id="selection-delete"',
            'id="selection-clear"',
        ], false);
    }

    public function test_the_selection_count_is_announced(): void
    {
        // The count is the only thing that says how much the toolbar is about to act on.
        $this->get('/tasks')->assertOk()->assertSee('id="selection-count" aria-live="polite"', false);
    }

    public function test_the_display_chips_sit_outside_the_list_panel(): void
    {
        // Inside it they would vanish on the board, which is the one layout where nothing else
        // says what is filtered.
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="display-summary"',
            'id="list-view"',
        ], false);
    }

    public function test_every_date_field_is_the_shared_component(): void
    {
        // A bare <input type="date"> is drawn differently by every browser, so it would be the
        // one field on the page that does not look like the app.
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertSame(
            substr_count($html, 'type="date"'),
            substr_count($html, 'data-date-field='),
            'A date input is not wrapped in <x-date-field>'
        );
    }

    public function test_a_project_board_has_a_way_back_to_the_projects(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="project-crumb"',
            'id="project-back"',
            'id="project-crumb-name"',
            'id="board-view"',
        ], false);
    }

    public function test_the_task_dialog_offers_the_card_colours(): void
    {
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertStringContainsString('id="detail-color"', $html);
        $this->assertStringContainsString('role="radiogroup"', $html);
        $this->assertSame(9, substr_count($html, 'data-color='));
    }
}
