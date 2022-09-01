<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        \App\Models\User::factory(5)->create();

        \App\Models\User::factory()->create([
            'name'      => 'Test User',
            'email'     => 'test@test.com',
            'password'  => Hash::make('test'),
            'api_token' => 'v3OGwOdUfeYMv81E3bycXy2Cwz0DoyaC24HQIapVd9vGXp3qJP1Mb2lEHT2v', // Str::random(60),
        ]);
    }
}
