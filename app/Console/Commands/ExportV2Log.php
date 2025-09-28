<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ExportV2Log extends Command
{
    protected $signature = 'log:export {days=1 : The number of days to export logs for}';
    protected $description = 'Export v2_log table records of the specified number of days to a file';

    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
        $days = $this->argument('days');
        $date = Carbon::now()->subDays((float) $days)->startOfDay();

        $logs = DB::table('v2_log')
            ->where('created_at', '>=', $date->timestamp)
            ->get();

        $fileName = "v2_logs_" . Carbon::now()->format('Y_m_d_His') . ".csv";
        $handle = fopen(storage_path("logs/$fileName"), 'w');

        // Based on your table structure
        fputcsv($handle, ['Level', 'ID', 'Title', 'Host', 'URI', 'Method', 'Data', 'IP', 'Context', 'Created At', 'Updated At']);

        foreach ($logs as $log) {
            fputcsv($handle, [
                $log->level,
                $log->id,
                $log->title,
                $log->host,
                $log->uri,
                $log->method,
                $log->data,
                $log->ip,
                $log->context,
                Carbon::createFromTimestamp($log->created_at)->toDateTimeString(),
                Carbon::createFromTimestamp($log->updated_at)->toDateTimeString()
            ]);
        }

        fclose($handle);
        $this->info("Log successfully exported to: " . storage_path("logs/$fileName"));
    }
}
