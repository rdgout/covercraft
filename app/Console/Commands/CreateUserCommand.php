<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUserCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create
                            {email : User email address}
                            {name : User full name}
                            {--team= : Team slug to add user to (creates if not exists)}
                            {--team-name= : Team name if creating new team}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new user with a random password';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');
        $name = $this->argument('name');
        $teamSlug = $this->option('team');
        $teamName = $this->option('team-name');

        if (User::where('email', $email)->exists()) {
            $this->error("User with email {$email} already exists.");

            return self::FAILURE;
        }

        $password = Str::random(16);

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
        ]);

        $this->info('User created successfully!');

        if ($teamSlug) {
            $team = Team::where('slug', $teamSlug)->first();

            if (! $team) {
                if (! $teamName) {
                    $this->error("Team with slug '{$teamSlug}' not found. Please provide --team-name to create a new team.");

                    return self::FAILURE;
                }

                $team = Team::create([
                    'name' => $teamName,
                    'slug' => $teamSlug,
                ]);

                $this->info("Created new team: {$teamName}");
            }

            $user->teams()->attach($team);
            $this->info("User added to team: {$team->name}");
        }

        $this->newLine();
        $this->info('=================================');
        $this->line("Email: {$email}");
        $this->line("Password: {$password}");
        $this->info('=================================');
        $this->warn('SAVE THIS PASSWORD NOW - it will not be shown again!');

        return self::SUCCESS;
    }
}
