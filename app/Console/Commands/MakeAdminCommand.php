<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;

class MakeAdminCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:make-admin {email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user admin by email';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $email = $this->argument('email');
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            $this->error("User with email {$email} not found.");
            return 1;
        }
        
        $user->update([
            'is_admin' => true,
            'role' => 'admin'
        ]);
        
        $this->info("User {$user->name} ({$email}) is now an admin.");
        return 0;
    }
}
