<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'role' => 'foster',
                'name' => 'Test User',
                'password' => bcrypt('password'),
                'push_notifications_enabled' => true,
            ],
        );

        $this->call([
            SampleAppDataSeeder::class,
        ]);
    }
}
