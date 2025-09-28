<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Log as LogModel;
use App\Utils\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\SupervisorRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;
use Laravel\Horizon\WaitTimeCalculator;
use App\Helpers\ResponseEnum;

class SystemController extends Controller
{
    public function getSystemStatus()
    {
        $data = [
            'schedule' => $this->getScheduleStatus(),
            'horizon' => $this->getHorizonStatus(),
            'schedule_last_runtime' => Cache::get(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null)),
            'logs' => $this->getLogStatistics()
        ];
        return $this->success($data);
    }

    /**
     * Get log statistics information
     *
     * @return array Log count statistics by level
     */
    protected function getLogStatistics(): array
    {
        // Initialize log statistics array
        $statistics = [
            'info' => 0,
            'warning' => 0,
            'error' => 0,
            'total' => 0
        ];

        if (class_exists(LogModel::class) && LogModel::count() > 0) {
            $statistics['info'] = LogModel::where('level', 'INFO')->count();
            $statistics['warning'] = LogModel::where('level', 'WARNING')->count();
            $statistics['error'] = LogModel::where('level', 'ERROR')->count();
            $statistics['total'] = LogModel::count();

            return $statistics;
        }
        return $statistics;
    }

    public function getQueueWorkload(WorkloadRepository $workload)
    {
        return $this->success(collect($workload->get())->sortBy('name')->values()->toArray());
    }

    protected function getScheduleStatus(): bool
    {
        return (time() - 120) < Cache::get(CacheKey::get('SCHEDULE_LAST_CHECK_AT', null));
    }

    protected function getHorizonStatus(): bool
    {
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            return false;
        }

        return collect($masters)->contains(function ($master) {
            return $master->status === 'paused';
        }) ? false : true;
    }

    public function getQueueStats()
    {
        $data = [
            'failedJobs' => app(JobRepository::class)->countRecentlyFailed(),
            'jobsPerMinute' => app(MetricsRepository::class)->jobsProcessedPerMinute(),
            'pausedMasters' => $this->totalPausedMasters(),
            'periods' => [
                'failedJobs' => config('horizon.trim.recent_failed', config('horizon.trim.failed')),
                'recentJobs' => config('horizon.trim.recent'),
            ],
            'processes' => $this->totalProcessCount(),
            'queueWithMaxRuntime' => app(MetricsRepository::class)->queueWithMaximumRuntime(),
            'queueWithMaxThroughput' => app(MetricsRepository::class)->queueWithMaximumThroughput(),
            'recentJobs' => app(JobRepository::class)->countRecent(),
            'status' => $this->getHorizonStatus(),
            'wait' => collect(app(WaitTimeCalculator::class)->calculate())->take(1),
        ];
        return $this->success($data);
    }

    /**
     * Get the total process count across all supervisors.
     *
     * @return int
     */
    protected function totalProcessCount()
    {
        $supervisors = app(SupervisorRepository::class)->all();

        return collect($supervisors)->reduce(function ($carry, $supervisor) {
            return $carry + collect($supervisor->processes)->sum();
        }, 0);
    }

    /**
     * Get the number of master supervisors that are currently paused.
     *
     * @return int
     */
    protected function totalPausedMasters()
    {
        if (!$masters = app(MasterSupervisorRepository::class)->all()) {
            return 0;
        }

        return collect($masters)->filter(function ($master) {
            return $master->status === 'paused';
        })->count();
    }

    public function getSystemLog(Request $request)
    {
        $current = $request->input('current') ? $request->input('current') : 1;
        $pageSize = $request->input('page_size') >= 10 ? $request->input('page_size') : 10;
        $level = $request->input('level');
        $keyword = $request->input('keyword');

        $builder = LogModel::orderBy('created_at', 'DESC')
            ->when($level, function ($query) use ($level) {
                return $query->where('level', strtoupper($level));
            })
            ->when($keyword, function ($query) use ($keyword) {
                return $query->where(function ($q) use ($keyword) {
                    $q->where('data', 'like', '%' . $keyword . '%')
                        ->orWhere('context', 'like', '%' . $keyword . '%')
                        ->orWhere('title', 'like', '%' . $keyword . '%')
                        ->orWhere('uri', 'like', '%' . $keyword . '%');
                });
            });

        $total = $builder->count();
        $res = $builder->forPage($current, $pageSize)
            ->get();

        return response([
            'data' => $res,
            'total' => $total
        ]);
    }

    public function getHorizonFailedJobs(Request $request, JobRepository $jobRepository)
    {
        $current = max(1, (int) $request->input('current', 1));
        $pageSize = max(10, (int) $request->input('page_size', 20));
        $offset = ($current - 1) * $pageSize;

        $failedJobs = collect($jobRepository->getFailed())
            ->sortByDesc('failed_at')
            ->slice($offset, $pageSize)
            ->values();

        $total = $jobRepository->countFailed();

        return response()->json([
            'data' => $failedJobs,
            'total' => $total,
            'current' => $current,
            'page_size' => $pageSize,
        ]);
    }

    /**
     * Clear system logs
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearSystemLog(Request $request)
    {
        $request->validate([
            'days' => 'integer|min:0|max:365',
            'level' => 'string|in:info,warning,error,all',
            'limit' => 'integer|min:100|max:10000'
        ], [
            'days.required' => 'Please specify how many days of logs to clear',
            'days.integer' => 'Days must be an integer',
            'days.min' => 'Days cannot be less than 1 day',
            'days.max' => 'Days cannot exceed 365 days',
            'level.in' => 'Log level can only be: info, warning, error, all',
            'limit.min' => 'Single clear count cannot be less than 100',
            'limit.max' => 'Single clear count cannot exceed 10000'
        ]);

        $days = $request->input('days', 30); // Default clear logs from 30 days ago
        $level = $request->input('level', 'all'); // Default clear all levels
        $limit = $request->input('limit', 1000); // Default single clear 1000 records

        try {
            $cutoffDate = now()->subDays($days);

            // Build query conditions
            $query = LogModel::where('created_at', '<', $cutoffDate->timestamp);

            if ($level !== 'all') {
                $query->where('level', strtoupper($level));
            }

            // Get the number of records to delete
            $totalCount = $query->count();

            if ($totalCount === 0) {
                return $this->success([
                    'message' => 'No log records found matching the criteria',
                    'deleted_count' => 0,
                    'total_count' => $totalCount
                ]);
            }

            // Batch delete to avoid deleting too much data at once
            $deletedCount = 0;
            $batchSize = min($limit, 1000); // Maximum 1000 records per batch

            while ($deletedCount < $limit && $deletedCount < $totalCount) {
                $remainingLimit = min($batchSize, $limit - $deletedCount);

                $batchQuery = LogModel::where('created_at', '<', $cutoffDate->timestamp);
                if ($level !== 'all') {
                    $batchQuery->where('level', strtoupper($level));
                }

                $idsToDelete = $batchQuery->limit($remainingLimit)->pluck('id');

                if ($idsToDelete->isEmpty()) {
                    break;
                }

                $batchDeleted = LogModel::whereIn('id', $idsToDelete)->delete();
                $deletedCount += $batchDeleted;

                                // Avoid long database connection occupation
                if ($deletedCount % 5000 === 0) {
                    usleep(100000); // Pause for 0.1 seconds
                }
            }

            return $this->success([
                'message' => 'Log clearing completed',
                'deleted_count' => $deletedCount,
                'total_count' => $totalCount,
                'remaining_count' => max(0, $totalCount - $deletedCount)
            ]);

        } catch (\Exception $e) {
            return $this->fail(ResponseEnum::HTTP_ERROR, null, 'Failed to clear logs: ' . $e->getMessage());
        }
    }

    /**
     * Get log clearing statistics information
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getLogClearStats(Request $request)
    {
        $days = $request->input('days', 30);
        $level = $request->input('level', 'all');

        try {
            $cutoffDate = now()->subDays($days);

            $query = LogModel::where('created_at', '<', $cutoffDate->timestamp);
            if ($level !== 'all') {
                $query->where('level', strtoupper($level));
            }

            $stats = [
                'days' => $days,
                'level' => $level,
                'cutoff_date' => $cutoffDate->format(format: 'Y-m-d H:i:s'),
                'total_logs' => LogModel::count(),
                'logs_to_clear' => $query->count(),
                'oldest_log' => LogModel::orderBy('created_at', 'asc')->first(),
                'newest_log' => LogModel::orderBy('created_at', 'desc')->first(),
            ];

            return $this->success($stats);

        } catch (\Exception $e) {
            return $this->fail(ResponseEnum::HTTP_ERROR, null, 'Failed to get statistics: ' . $e->getMessage());
        }
    }
}
