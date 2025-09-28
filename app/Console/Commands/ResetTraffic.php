<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\TrafficResetLog;
use App\Services\TrafficResetService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ResetTraffic extends Command
{
  protected $signature = 'reset:traffic {--fix-null : Fix mode, recalculate users with null next_reset_at} {--force : Force mode, recalculate reset time for all users}';

  protected $description = 'Traffic reset - process all users that need reset';

  public function __construct(
    private readonly TrafficResetService $trafficResetService
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $fixNull = $this->option('fix-null');
    $force = $this->option('force');

    $this->info('🚀 Starting traffic reset task...');

    if ($fixNull) {
      $this->warn('🔧 Fix mode - will recalculate users with null next_reset_at');
    } elseif ($force) {
      $this->warn('⚡ Force mode - will recalculate reset time for all users');
    }

    try {
      $result = $fixNull ? $this->performFix() : ($force ? $this->performForce() : $this->performReset());
      $this->displayResults($result, $fixNull || $force);
      return self::SUCCESS;

    } catch (\Exception $e) {
      $this->error("❌ Task execution failed: {$e->getMessage()}");

      Log::error('Traffic reset command execution failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
      ]);

      return self::FAILURE;
    }
  }

  private function displayResults(array $result, bool $isSpecialMode): void
  {
    $this->info("✅ Task completed!\n");

    if ($isSpecialMode) {
      $this->displayFixResults($result);
    } else {
      $this->displayExecutionResults($result);
    }
  }

  private function displayFixResults(array $result): void
  {
    $this->info("📊 Fix results summary:");
    $this->info("🔍 Total users found: {$result['total_found']}");
    $this->info("✅ Successfully fixed: {$result['total_fixed']}");
    $this->info("⏱️  Total execution time: {$result['duration']} seconds");

    if ($result['error_count'] > 0) {
      $this->warn("⚠️  Error count: {$result['error_count']}");
      $this->warn("Please check logs for detailed error information");
    } else {
      $this->info("✨ No errors occurred");
    }

    if ($result['total_found'] > 0) {
      $avgTime = round($result['duration'] / $result['total_found'], 4);
      $this->info("⚡ Average processing speed: {$avgTime} seconds/user");
    }
  }



  private function displayExecutionResults(array $result): void
  {
    $this->info("📊 Execution results summary:");
    $this->info("👥 Total users processed: {$result['total_processed']}");
    $this->info("🔄 Users reset count: {$result['total_reset']}");
    $this->info("⏱️  Total execution time: {$result['duration']} seconds");

    if ($result['error_count'] > 0) {
      $this->warn("⚠️  Error count: {$result['error_count']}");
      $this->warn("Please check logs for detailed error information");
    } else {
      $this->info("✨ No errors occurred");
    }

    if ($result['total_processed'] > 0) {
      $avgTime = round($result['duration'] / $result['total_processed'], 4);
      $this->info("⚡ Average processing speed: {$avgTime} seconds/user");
    }
  }

  private function performReset(): array
  {
    $startTime = microtime(true);
    $totalResetCount = 0;
    $errors = [];

    $users = $this->getResetQuery()->get();

    if ($users->isEmpty()) {
      $this->info("😴 Currently no users need to be reset");
      return [
        'total_processed' => 0,
        'total_reset' => 0,
        'error_count' => 0,
        'duration' => round(microtime(true) - $startTime, 2),
      ];
    }

    $this->info("Found {$users->count()} users that need to be reset");

    foreach ($users as $user) {
      try {
        $totalResetCount += (int) $this->trafficResetService->checkAndReset($user, TrafficResetLog::SOURCE_CRON);
      } catch (\Exception $e) {
        $errors[] = [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ];
        Log::error('User traffic reset failed', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'total_processed' => $users->count(),
      'total_reset' => $totalResetCount,
      'error_count' => count($errors),
      'duration' => round(microtime(true) - $startTime, 2),
    ];
  }

  private function performFix(): array
  {
    $startTime = microtime(true);
    $nullUsers = $this->getNullResetTimeUsers();

    if ($nullUsers->isEmpty()) {
      $this->info("✅ No users found with null next_reset_at");
      return [
        'total_found' => 0,
        'total_fixed' => 0,
        'error_count' => 0,
        'duration' => round(microtime(true) - $startTime, 2),
      ];
    }

    $this->info("🔧 Found {$nullUsers->count()} users with null next_reset_at, starting to fix...");

    $fixedCount = 0;
    $errors = [];

    foreach ($nullUsers as $user) {
      try {
        $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);
        if ($nextResetTime) {
          $user->next_reset_at = $nextResetTime->timestamp;
          $user->save();
          $fixedCount++;
        }
      } catch (\Exception $e) {
        $errors[] = [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ];
        Log::error('Failed to fix user next_reset_at', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'total_found' => $nullUsers->count(),
      'total_fixed' => $fixedCount,
      'error_count' => count($errors),
      'duration' => round(microtime(true) - $startTime, 2),
    ];
  }

  private function performForce(): array
  {
    $startTime = microtime(true);
    $allUsers = $this->getAllUsers();

    if ($allUsers->isEmpty()) {
      $this->info("✅ No users found that need processing");
      return [
        'total_found' => 0,
        'total_fixed' => 0,
        'error_count' => 0,
        'duration' => round(microtime(true) - $startTime, 2),
      ];
    }

    $this->info("⚡ Found {$allUsers->count()} users, starting to recalculate reset time...");

    $fixedCount = 0;
    $errors = [];

    foreach ($allUsers as $user) {
      try {
        $nextResetTime = $this->trafficResetService->calculateNextResetTime($user);
        if ($nextResetTime) {
          $user->next_reset_at = $nextResetTime->timestamp;
          $user->save();
          $fixedCount++;
        }
      } catch (\Exception $e) {
        $errors[] = [
          'user_id' => $user->id,
          'email' => $user->email,
          'error' => $e->getMessage(),
        ];
        Log::error('Failed to force recalculate user next_reset_at', [
          'user_id' => $user->id,
          'error' => $e->getMessage(),
        ]);
      }
    }

    return [
      'total_found' => $allUsers->count(),
      'total_fixed' => $fixedCount,
      'error_count' => count($errors),
      'duration' => round(microtime(true) - $startTime, 2),
    ];
  }



  private function getResetQuery()
  {
    return User::where('next_reset_at', '<=', time())
      ->whereNotNull('next_reset_at')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', 0)
      ->whereNotNull('plan_id');
  }



  private function getNullResetTimeUsers()
  {
    return User::whereNull('next_reset_at')
      ->whereNotNull('plan_id')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', 0)
      ->with('plan:id,name,reset_traffic_method')
      ->get();
  }

  private function getAllUsers()
  {
    return User::whereNotNull('plan_id')
      ->where(function ($query) {
        $query->where('expired_at', '>', time())
          ->orWhereNull('expired_at');
      })
      ->where('banned', 0)
      ->with('plan:id,name,reset_traffic_method')
      ->get();
  }

}