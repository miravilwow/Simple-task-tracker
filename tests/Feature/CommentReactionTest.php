<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\CategoryComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommentReactionTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private CategoryComment $comment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::factory()->create();
        $this->comment = $this->category->comments()->create(['body' => 'Shipping tomorrow.']);
    }

    public function test_adds_a_reaction_and_takes_it_back(): void
    {
        $this->react(['emoji' => '👍', 'reacted' => true, 'reactor' => 'browser-a'])
            ->assertOk()
            ->assertJsonPath('data.reactions.0.emoji', '👍')
            ->assertJsonPath('data.reactions.0.count', 1);

        $this->react(['emoji' => '👍', 'reacted' => false, 'reactor' => 'browser-a'])
            ->assertOk()
            ->assertJsonPath('data.reactions', []);
    }

    public function test_the_reply_to_a_reaction_knows_it_was_mine(): void
    {
        // The token arrives in the body here and in the query string on a read, so the resource
        // has to look in both. Otherwise the button reports itself unpressed the moment it is
        // pressed, and only a reload corrects it.
        $this->react(['emoji' => '👍', 'reacted' => true, 'reactor' => 'browser-a'])
            ->assertOk()
            ->assertJsonPath('data.reactions.0.mine', true);
    }

    public function test_the_same_browser_cannot_count_itself_twice(): void
    {
        // The unique index does the work, so a double tap cannot leave a second row behind.
        $this->react(['emoji' => '🎉', 'reacted' => true, 'reactor' => 'browser-a'])->assertOk();

        $this->react(['emoji' => '🎉', 'reacted' => true, 'reactor' => 'browser-a'])
            ->assertOk()
            ->assertJsonPath('data.reactions.0.count', 1);

        $this->assertDatabaseCount('comment_reactions', 1);
    }

    public function test_two_browsers_add_up(): void
    {
        $this->react(['emoji' => '🚀', 'reacted' => true, 'reactor' => 'browser-a'])->assertOk();

        $this->react(['emoji' => '🚀', 'reacted' => true, 'reactor' => 'browser-b'])
            ->assertOk()
            ->assertJsonPath('data.reactions.0.count', 2);
    }

    public function test_removing_a_reaction_that_was_never_there_is_not_an_error(): void
    {
        // Safe to retry: the client may resend after a dropped response.
        $this->react(['emoji' => '👀', 'reacted' => false, 'reactor' => 'browser-a'])
            ->assertOk()
            ->assertJsonPath('data.reactions', []);
    }

    public function test_mine_is_worked_out_for_the_browser_that_asks(): void
    {
        $this->react(['emoji' => '❤️', 'reacted' => true, 'reactor' => 'browser-a'])->assertOk();

        $mine = $this->getJson("/api/categories/{$this->category->id}/comments?reactor=browser-a");
        $mine->assertJsonPath('data.0.reactions.0.mine', true);

        $theirs = $this->getJson("/api/categories/{$this->category->id}/comments?reactor=browser-b");
        $theirs->assertJsonPath('data.0.reactions.0.mine', false);
        $theirs->assertJsonPath('data.0.reactions.0.count', 1);
    }

    public function test_a_thread_with_no_reactor_reports_none_as_mine(): void
    {
        $this->react(['emoji' => '🙌', 'reacted' => true, 'reactor' => 'browser-a'])->assertOk();

        $this->getJson("/api/categories/{$this->category->id}/comments")
            ->assertJsonPath('data.0.reactions.0.mine', false);
    }

    public function test_rejects_an_emoji_that_is_not_in_the_set(): void
    {
        // The value is rendered straight into the page, so the set is closed server-side.
        $this->react(['emoji' => '💀', 'reacted' => true, 'reactor' => 'browser-a'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('emoji');
    }

    public function test_requires_the_value_it_is_setting_and_the_browser_setting_it(): void
    {
        $this->react(['emoji' => '👍'])
            ->assertStatus(400)
            ->assertJsonValidationErrors(['reacted', 'reactor']);
    }

    public function test_a_comment_cannot_be_reacted_to_through_another_project(): void
    {
        $other = Category::factory()->create(['name' => 'Elsewhere']);

        $this->patchJson(
            "/api/categories/{$other->id}/comments/{$this->comment->id}/reactions",
            ['emoji' => '👍', 'reacted' => true, 'reactor' => 'browser-a']
        )->assertNotFound();

        $this->assertDatabaseCount('comment_reactions', 0);
    }

    public function test_deleting_a_comment_takes_its_reactions_with_it(): void
    {
        $this->react(['emoji' => '👍', 'reacted' => true, 'reactor' => 'browser-a'])->assertOk();

        $this->deleteJson("/api/categories/{$this->category->id}/comments/{$this->comment->id}")->assertOk();

        $this->assertDatabaseCount('comment_reactions', 0);
    }

    public function test_listing_a_thread_does_not_run_a_query_per_comment(): void
    {
        foreach (range(1, 5) as $number) {
            $comment = $this->category->comments()->create(['body' => "Note {$number}"]);
            $comment->reactions()->create(['emoji' => '👍', 'reactor' => "browser-{$number}"]);
        }

        $queries = 0;
        \DB::listen(function () use (&$queries) {
            $queries++;
        });

        $this->getJson("/api/categories/{$this->category->id}/comments")->assertOk();

        // One for the project, one for the comments, one for every comment's reactions at once.
        $this->assertSame(3, $queries);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function react(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->patchJson(
            "/api/categories/{$this->category->id}/comments/{$this->comment->id}/reactions",
            $payload
        );
    }
}
