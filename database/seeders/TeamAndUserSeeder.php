<?php

namespace Database\Seeders;

use App\Models\Repository;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TeamAndUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::exists()) {
            return;
        }

        $team = Team::create([
            'name' => 'Default Team',
            'slug' => 'default-team',
        ]);

        $password = Str::random(16);

        $user = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make($password),
        ]);

        $user->teams()->attach($team);

        Repository::whereNull('team_id')->update(['team_id' => $team->id]);

        $this->command->info('=================================');
        $this->command->info('Initial setup completed!');
        $this->command->info('=================================');
        $this->command->line('Email: admin@example.com');
        $this->command->line("Password: {$password}");
        $this->command->info('=================================');
        $this->command->warn('SAVE THIS PASSWORD NOW - it will not be shown again!');
    }
}
