<?php

namespace Database\Seeders;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Database\Seeder;

class TaskSeeder extends Seeder
{
    public function run(): void
    {
        // [title, description, priority, status, hours ago]
        $tasks = [
            ['Fix checkout payment timeout', 'Customers see a 504 after 30 seconds on the payment step.', TaskPriority::High, TaskStatus::Pending, 30],
            ['Rotate expired API keys', null, TaskPriority::High, TaskStatus::Pending, 6],
            ['Set up Git repository', 'Initial commit and branch protection on main.', TaskPriority::High, TaskStatus::Completed, 48],
            ['Write README setup steps', 'Cover composer, npm, .env, migrations, and tests.', TaskPriority::Medium, TaskStatus::Pending, 20],
            ['Review pull request #42', null, TaskPriority::Medium, TaskStatus::Pending, 3],
            ['Design task list empty state', null, TaskPriority::Medium, TaskStatus::Completed, 40],
            ['Update project dependencies', 'Run composer update and npm update, then retest.', TaskPriority::Low, TaskStatus::Pending, 12],
            ['Clean up unused CSS classes', null, TaskPriority::Low, TaskStatus::Completed, 36],
        ];

        foreach ($tasks as [$title, $description, $priority, $status, $hoursAgo]) {
            $createdAt = now()->subHours($hoursAgo);

            Task::factory()->create([
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $status,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ]);
        }
    }
}
