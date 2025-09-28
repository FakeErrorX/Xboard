<?php

namespace App\Console\Commands;

use App\Services\MailService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendRemindMail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'send:remindMail 
                            {--chunk-size=500 : Number of users to process per batch}
                            {--force : Force execution, skip confirmation}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send reminder emails';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        if (!admin_setting('remind_mail_enable', false)) {
            $this->warn('Email reminder feature is not enabled');
            return 0;
        }

        $chunkSize = max(100, min(2000, (int) $this->option('chunk-size')));
        $mailService = new MailService();

        $totalUsers = $mailService->getTotalUsersNeedRemind();
        if ($totalUsers === 0) {
            $this->info('No users need reminder emails');
            return 0;
        }

        $this->displayInfo($totalUsers, $chunkSize);

        if (!$this->option('force') && !$this->confirm("Are you sure you want to send reminder emails to {$totalUsers} users?")) {
            return 0;
        }

        $startTime = microtime(true);
        $progressBar = $this->output->createProgressBar((int) ceil($totalUsers / $chunkSize));
        $progressBar->start();

        $statistics = $mailService->processUsersInChunks($chunkSize, function () use ($progressBar) {
            $progressBar->advance();
        });

        $progressBar->finish();
        $this->newLine();

        $this->displayResults($statistics, microtime(true) - $startTime);
        $this->logResults($statistics);

        return 0;
    }

    private function displayInfo(int $totalUsers, int $chunkSize): void
    {
        $this->table(['Item', 'Value'], [
            ['Users to process', number_format($totalUsers)],
            ['Batch size', $chunkSize],
            ['Estimated batches', ceil($totalUsers / $chunkSize)],
        ]);
    }

    private function displayResults(array $stats, float $duration): void
    {
        $this->info('✅ Reminder email sending completed!');

        $this->table(['Statistics', 'Count'], [
            ['Total processed users', number_format($stats['processed_users'])],
            ['Expiry reminder emails', number_format($stats['expire_emails'])],
            ['Traffic reminder emails', number_format($stats['traffic_emails'])],
            ['Skipped users', number_format($stats['skipped'])],
            ['Error count', number_format($stats['errors'])],
            ['Total time taken', round($duration, 2) . ' seconds'],
            ['Average speed', round($stats['processed_users'] / max($duration, 0.1), 1) . ' users/sec'],
        ]);

        if ($stats['errors'] > 0) {
            $this->warn("⚠️  {$stats['errors']} users' emails failed to send, please check the logs");
        }
    }

    private function logResults(array $statistics): void
    {
        Log::info('SendRemindMail command completed', ['statistics' => $statistics]);
    }
}
