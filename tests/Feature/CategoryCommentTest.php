<?php

namespace Tests\Feature;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_adds_a_comment_to_a_project(): void
    {
        $category = Category::factory()->create();

        $this->postJson("/api/categories/{$category->id}/comments", ['body' => 'Blocked on the API key.'])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Blocked on the API key.');

        $this->assertDatabaseHas('category_comments', [
            'category_id' => $category->id,
            'body' => 'Blocked on the API key.',
        ]);
    }

    public function test_lists_a_projects_comments_oldest_first(): void
    {
        // A thread is read from the top, unlike the activity feed.
        $category = Category::factory()->create();

        $this->postJson("/api/categories/{$category->id}/comments", ['body' => 'First'])->assertCreated();
        $this->postJson("/api/categories/{$category->id}/comments", ['body' => 'Second'])->assertCreated();

        $bodies = array_column($this->getJson("/api/categories/{$category->id}/comments")->json('data'), 'body');

        $this->assertSame(['First', 'Second'], $bodies);
    }

    public function test_rejects_an_empty_comment(): void
    {
        $category = Category::factory()->create();

        $this->postJson("/api/categories/{$category->id}/comments", ['body' => '   '])
            ->assertStatus(400)
            ->assertJsonValidationErrors('body');

        $this->assertDatabaseCount('category_comments', 0);
    }

    public function test_rejects_a_comment_that_is_too_long(): void
    {
        $category = Category::factory()->create();

        $this->postJson("/api/categories/{$category->id}/comments", ['body' => str_repeat('a', 1001)])
            ->assertStatus(400)
            ->assertJsonValidationErrors('body');
    }

    public function test_deletes_a_comment(): void
    {
        $category = Category::factory()->create();
        $comment = $category->comments()->create(['body' => 'Never mind']);

        $this->deleteJson("/api/categories/{$category->id}/comments/{$comment->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Comment deleted.');

        $this->assertModelMissing($comment);
    }

    public function test_a_comment_cannot_be_deleted_through_another_project(): void
    {
        $mine = Category::factory()->create(['name' => 'Work']);
        $other = Category::factory()->create(['name' => 'Home']);
        $comment = $other->comments()->create(['body' => 'Not yours']);

        $this->deleteJson("/api/categories/{$mine->id}/comments/{$comment->id}")->assertNotFound();

        $this->assertModelExists($comment);
    }

    public function test_a_deleted_project_keeps_its_comments_for_a_restore(): void
    {
        $category = Category::factory()->create();
        $category->comments()->create(['body' => 'Still here']);

        $this->deleteJson("/api/categories/{$category->id}")->assertOk();

        // A soft delete removes nothing, so the cascade never fires and the thread survives it.
        $this->assertDatabaseCount('category_comments', 1);

        $this->patchJson("/api/categories/{$category->id}/restore")->assertOk();
        $this->getJson("/api/categories/{$category->id}/comments")
            ->assertOk()
            ->assertJsonPath('data.0.body', 'Still here');
    }

    public function test_the_project_list_carries_a_comment_count(): void
    {
        $category = Category::factory()->create();
        $category->comments()->create(['body' => 'One']);
        $category->comments()->create(['body' => 'Two']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.comment_count', 2);
    }

    public function test_comments_are_404_for_a_missing_project(): void
    {
        $this->getJson('/api/categories/999/comments')->assertNotFound();
        $this->postJson('/api/categories/999/comments', ['body' => 'Hello'])->assertNotFound();
    }
}
