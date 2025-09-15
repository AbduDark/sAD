<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Subscription;
use App\Models\Rating;
use App\Models\Favorite;
use App\Models\Comment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create Admin User
        User::create([
            'name' => 'Admin User',
            'email' => 'rose@academy.com',
            'password' => Hash::make('1'),
            'phone' => '01234567890',
            'is_admin' => true,
            'gender' => 'male',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        User::create([
            'name' => 'Admin User',
            'email' => 'rose1@academy.com',
            'password' => Hash::make('1'),
            'phone' => '01234567890',
            'is_admin' => true,
            'gender' => 'male',
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        // Create Regular User
        $user = User::create([
            'name' => 'طالب تجريبي',
            'email' => 'gom3a@example.com',
            'password' => Hash::make('1'),
            'phone' => '01987654321',
            'gender' => 'male',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);
        $user = User::create([
            'name' => 'طالب تجريبي',
            'email' => 'dark@example.com',
            'password' => Hash::make('1'),
            'phone' => '01987654321',
            'gender' => 'male',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);



    }
}
