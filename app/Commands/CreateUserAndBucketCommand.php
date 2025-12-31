<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CreateUserAndBucketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:create-user-and-bucket-command';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Creates a user and a bucket on AWS';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Input for username
        $username = $this->ask('Enter the username for the new IAM user (lowercase, dashes are allowed, no underscores)');
        $bucketName = $this->ask('Enter the name for the new S3 bucket (lowercase, dashes are allowed, no underscores)');
        $this->info('User created successfully for ' . $username);
        $this->info('Bucket created successfully for ' . $bucketName);
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
