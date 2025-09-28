<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCardCode;
use App\Models\GiftCardTemplate;
use App\Models\GiftCardUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class GiftCardController extends Controller
{
    /**
     * Get gift card template list
     */
    public function templates(Request $request)
    {
        $request->validate([
            'type' => 'integer|min:1|max:10',
            'status' => 'integer|in:0,1',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:1000',
        ]);

        $query = GiftCardTemplate::query();

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 15);
        $templates = $query->orderBy('sort', 'asc')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $templates->getCollection()->map(function ($template) {
            return [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'type' => $template->type,
                'type_name' => $template->type_name,
                'status' => $template->status,
                'conditions' => $template->conditions,
                'rewards' => $template->rewards,
                'limits' => $template->limits,
                'special_config' => $template->special_config,
                'icon' => $template->icon,
                'background_image' => $template->background_image,
                'theme_color' => $template->theme_color,
                'sort' => $template->sort,
                'admin_id' => $template->admin_id,
                'created_at' => $template->created_at,
                'updated_at' => $template->updated_at,
                // Statistics
                'codes_count' => $template->codes()->count(),
                'used_count' => $template->usages()->count(),
            ];
        })->values();

        return $this->paginate( $templates);
    }

    /**
     * Create gift card template
     */
    public function createTemplate(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => [
                'required',
                'integer',
                Rule::in(array_keys(GiftCardTemplate::getTypeMap()))
            ],
            'status' => 'boolean',
            'conditions' => 'nullable|array',
            'rewards' => 'required|array',
            'limits' => 'nullable|array',
            'special_config' => 'nullable|array',
            'icon' => 'nullable|string|max:255',
            'background_image' => 'nullable|string|url|max:255',
            'theme_color' => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort' => 'integer|min:0',
        ], [
            'name.required' => 'Gift card name cannot be empty',
            'type.required' => 'Gift card type cannot be empty',
            'type.in' => 'Invalid gift card type',
            'rewards.required' => 'Reward configuration cannot be empty',
            'theme_color.regex' => 'Theme color format is incorrect',
            'background_image.url' => 'Background image must be a valid URL',
        ]);

        try {
            $template = GiftCardTemplate::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'type' => $request->input('type'),
                'status' => $request->input('status', true),
                'conditions' => $request->input('conditions'),
                'rewards' => $request->input('rewards'),
                'limits' => $request->input('limits'),
                'special_config' => $request->input('special_config'),
                'icon' => $request->input('icon'),
                'background_image' => $request->input('background_image'),
                'theme_color' => $request->input('theme_color', '#1890ff'),
                'sort' => $request->input('sort', 0),
                'admin_id' => $request->user()->id,
                'created_at' => time(),
                'updated_at' => time(),
            ]);

            return $this->success($template);
        } catch (\Exception $e) {
            Log::error('Failed to create gift card template', [
                'admin_id' => $request->user()->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Creation failed']);
        }
    }

    /**
     * Update gift card template
     */
    public function updateTemplate(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|integer|exists:v2_gift_card_template,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'type' => [
                'sometimes',
                'required',
                'integer',
                Rule::in(array_keys(GiftCardTemplate::getTypeMap()))
            ],
            'status' => 'sometimes|boolean',
            'conditions' => 'sometimes|nullable|array',
            'rewards' => 'sometimes|required|array',
            'limits' => 'sometimes|nullable|array',
            'special_config' => 'sometimes|nullable|array',
            'icon' => 'sometimes|nullable|string|max:255',
            'background_image' => 'sometimes|nullable|string|url|max:255',
            'theme_color' => 'sometimes|nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'sort' => 'sometimes|integer|min:0',
        ]);

        $template = GiftCardTemplate::find($validatedData['id']);
        if (!$template) {
            return $this->fail([404, 'Template not found']);
        }

        try {
            $updateData = collect($validatedData)->except('id')->all();

            if (empty($updateData)) {
                return $this->success($template);
            }

            $updateData['updated_at'] = time();

            $template->update($updateData);

            return $this->success($template->fresh());
        } catch (\Exception $e) {
            Log::error('Failed to update gift card template', [
                'admin_id' => $request->user()->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Update failed']);
        }
    }

    /**
     * Delete gift card template
     */
    public function deleteTemplate(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:v2_gift_card_template,id',
        ]);

        $template = GiftCardTemplate::find($request->input('id'));
        if (!$template) {
            return $this->fail([404, 'Template not found']);
        }

        // Check if there are associated redemption codes
        if ($template->codes()->exists()) {
            return $this->fail([400, 'Cannot delete template with existing redemption codes']);
        }

        try {
            $template->delete();
            return $this->success(true);
        } catch (\Exception $e) {
            Log::error('Failed to delete gift card template', [
                'admin_id' => $request->user()->id,
                'template_id' => $template->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Delete failed']);
        }
    }

    /**
     * Generate redemption codes
     */
    public function generateCodes(Request $request)
    {
        $request->validate([
            'template_id' => 'required|integer|exists:v2_gift_card_template,id',
            'count' => 'required|integer|min:1|max:10000',
            'prefix' => 'nullable|string|max:10|regex:/^[A-Z0-9]*$/',
            'expires_hours' => 'nullable|integer|min:1',
            'max_usage' => 'integer|min:1|max:1000',
        ], [
            'template_id.required' => 'Please select a gift card template',
            'count.required' => 'Please specify generation count',
            'count.max' => 'Maximum 10000 redemption codes per batch',
            'prefix.regex' => 'Prefix can only contain uppercase letters and numbers',
        ]);

        $template = GiftCardTemplate::find($request->input('template_id'));
        if (!$template->isAvailable()) {
            return $this->fail([400, 'Template has been disabled']);
        }

        try {
            $options = [
                'prefix' => $request->input('prefix', 'GC'),
                'max_usage' => $request->input('max_usage', 1),
            ];

            if ($request->has('expires_hours')) {
                $options['expires_at'] = time() + ($request->input('expires_hours') * 3600);
            }

            $batchId = GiftCardCode::batchGenerate(
                $request->input('template_id'),
                $request->input('count'),
                $options
            );

            // Query all redemption codes generated in this batch
            $codes = GiftCardCode::where('batch_id', $batchId)->get();

            // Check if CSV export is requested
            if ($request->input('download_csv')) {
                $headers = [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="gift_codes.csv"',
                ];
                $callback = function () use ($codes, $template) {
                    $handle = fopen('php://output', 'w');
                    // Header row
                    fputcsv($handle, [
                        'Redemption Code',
                        'Prefix',
                        'Expiry Date',
                        'Max Usage Count',
                        'Batch ID',
                        'Created Time',
                        'Template Name',
                        'Template Type',
                        'Template Rewards',
                        'Status',
                        'Used By',
                        'Used Time',
                        'Remarks'
                    ]);
                    foreach ($codes as $code) {
                        $expireDate = $code->expires_at ? date('Y-m-d H:i:s', $code->expires_at) : 'Permanent';
                        $createDate = date('Y-m-d H:i:s', $code->created_at);
                        $templateName = $template->name ?? '';
                        $templateType = $template->type ?? '';
                        $templateRewards = $template->rewards ? json_encode($template->rewards, JSON_UNESCAPED_UNICODE) : '';
                        // Status check
                        $status = $code->status_name;
                        $usedBy = $code->user_id ?? '';
                        $usedAt = $code->used_at ? date('Y-m-d H:i:s', $code->used_at) : '';
                        $remark = $code->remark ?? '';
                        fputcsv($handle, [
                            $code->code,
                            $code->prefix ?? '',
                            $expireDate,
                            $code->max_usage,
                            $code->batch_id,
                            $createDate,
                            $templateName,
                            $templateType,
                            $templateRewards,
                            $status,
                            $usedBy,
                            $usedAt,
                            $remark,
                        ]);
                    }
                    fclose($handle);
                };
                return response()->streamDownload($callback, 'gift_codes.csv', $headers);
            }

            Log::info('Batch generate redemption codes', [
                'admin_id' => $request->user()->id,
                'template_id' => $request->input('template_id'),
                'count' => $request->input('count'),
                'batch_id' => $batchId,
            ]);

            return $this->success([
                'batch_id' => $batchId,
                'count' => $request->input('count'),
                'message' => 'Generated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to generate redemption codes', [
                'admin_id' => $request->user()->id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Generation failed']);
        }
    }

    /**
     * Get redemption codes list
     */
    public function codes(Request $request)
    {
        $request->validate([
            'template_id' => 'integer|exists:v2_gift_card_template,id',
            'batch_id' => 'string',
            'status' => 'integer|in:0,1,2,3',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:500',
        ]);

        $query = GiftCardCode::with(['template', 'user']);

        if ($request->has('template_id')) {
            $query->where('template_id', $request->input('template_id'));
        }

        if ($request->has('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = $request->input('per_page', 15);
        $codes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $data = $codes->getCollection()->map(function ($code) {
            return [
                'id' => $code->id,
                'template_id' => $code->template_id,
                'template_name' => $code->template->name ?? '',
                'code' => $code->code,
                'batch_id' => $code->batch_id,
                'status' => $code->status,
                'status_name' => $code->status_name,
                'user_id' => $code->user_id,
                'user_email' => $code->user ? (substr($code->user->email ?? '', 0, 3) . '***@***') : null,
                'used_at' => $code->used_at,
                'expires_at' => $code->expires_at,
                'usage_count' => $code->usage_count,
                'max_usage' => $code->max_usage,
                'created_at' => $code->created_at,
            ];
        })->values();

        return $this->paginate($codes);
    }

    /**
     * Disable/Enable redemption codes
     */
    public function toggleCode(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:v2_gift_card_code,id',
            'action' => 'required|string|in:disable,enable',
        ]);

        $code = GiftCardCode::find($request->input('id'));
        if (!$code) {
            return $this->fail([404, 'Redemption code not found']);
        }

        try {
            if ($request->input('action') === 'disable') {
                $code->markAsDisabled();
            } else {
                if ($code->status === GiftCardCode::STATUS_DISABLED) {
                    $code->status = GiftCardCode::STATUS_UNUSED;
                    $code->save();
                }
            }

            return $this->success([
                'message' => $request->input('action') === 'disable' ? 'Disabled' : 'Enabled',
            ]);
        } catch (\Exception $e) {
            return $this->fail([500, 'Operation failed']);
        }
    }

    /**
     * Export redemption codes
     */
    public function exportCodes(Request $request)
    {
        $request->validate([
            'batch_id' => 'required|string|exists:v2_gift_card_code,batch_id',
        ]);

        $codes = GiftCardCode::where('batch_id', $request->input('batch_id'))
            ->orderBy('created_at', 'asc')
            ->get(['code']);

        $content = $codes->pluck('code')->implode("\n");

        return response($content)
            ->header('Content-Type', 'text/plain')
            ->header('Content-Disposition', 'attachment; filename="gift_cards_' . $request->input('batch_id') . '.txt"');
    }

    /**
     * Get usage records
     */
    public function usages(Request $request)
    {
        $request->validate([
            'template_id' => 'integer|exists:v2_gift_card_template,id',
            'user_id' => 'integer|exists:v2_user,id',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:500',
        ]);

        $query = GiftCardUsage::with(['template', 'code', 'user', 'inviteUser']);

        if ($request->has('template_id')) {
            $query->where('template_id', $request->input('template_id'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        $perPage = $request->input('per_page', 15);
        $usages = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $usages->transform(function ($usage) {
            return [
                'id' => $usage->id,
                'code' => $usage->code->code ?? '',
                'template_name' => $usage->template->name ?? '',
                'user_email' => $usage->user->email ?? '',
                'invite_user_email' => $usage->inviteUser ? (substr($usage->inviteUser->email ?? '', 0, 3) . '***@***') : null,
                'rewards_given' => $usage->rewards_given,
                'invite_rewards' => $usage->invite_rewards,
                'multiplier_applied' => $usage->multiplier_applied,
                'created_at' => $usage->created_at,
            ];
        })->values();
        return $this->paginate($usages);
    }

    /**
     * Get statistics data
     */
    public function statistics(Request $request)
    {
        $request->validate([
            'start_date' => 'date_format:Y-m-d',
            'end_date' => 'date_format:Y-m-d',
        ]);

        $startDate = $request->input('start_date', date('Y-m-d', strtotime('-30 days')));
        $endDate = $request->input('end_date', date('Y-m-d'));

        // Overall statistics
        $totalStats = [
            'templates_count' => GiftCardTemplate::count(),
            'active_templates_count' => GiftCardTemplate::where('status', 1)->count(),
            'codes_count' => GiftCardCode::count(),
            'used_codes_count' => GiftCardCode::where('status', GiftCardCode::STATUS_USED)->count(),
            'usages_count' => GiftCardUsage::count(),
        ];

        // Daily usage statistics
        $driver = DB::connection()->getDriverName();
        $dateExpression = "date(created_at, 'unixepoch')"; // Default for SQLite
        if ($driver === 'mysql') {
            $dateExpression = 'DATE(FROM_UNIXTIME(created_at))';
        } elseif ($driver === 'pgsql') {
            $dateExpression = 'date(to_timestamp(created_at))';
        }

        $dailyUsages = GiftCardUsage::selectRaw("{$dateExpression} as date, COUNT(*) as count")
            ->whereRaw("{$dateExpression} BETWEEN ? AND ?", [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Type statistics
        $typeStats = GiftCardUsage::with('template')
            ->selectRaw('template_id, COUNT(*) as count')
            ->groupBy('template_id')
            ->get()
            ->map(function ($item) {
                return [
                    'template_name' => $item->template->name ?? '',
                    'type_name' => $item->template->type_name ?? '',
                    'count' => $item->count ?? 0,
                ];
            });

        return $this->success([
            'total_stats' => $totalStats,
            'daily_usages' => $dailyUsages,
            'type_stats' => $typeStats,
        ]);
    }

    /**
     * Get all available gift card types
     */
    public function types()
    {
        return $this->success(GiftCardTemplate::getTypeMap());
    }

    /**
     * Update single redemption code
     */
    public function updateCode(Request $request)
    {
        $validatedData = $request->validate([
            'id' => 'required|integer|exists:v2_gift_card_code,id',
            'expires_at' => 'sometimes|nullable|integer',
            'max_usage' => 'sometimes|integer|min:1|max:1000',
            'status' => 'sometimes|integer|in:0,1,2,3',
        ]);

        $code = GiftCardCode::find($validatedData['id']);
        if (!$code) {
            return $this->fail([404, 'Gift card not found']);
        }

        try {
            $updateData = collect($validatedData)->except('id')->all();

            if (empty($updateData)) {
                return $this->success($code);
            }

            $updateData['updated_at'] = time();
            $code->update($updateData);

            return $this->success($code->fresh());
        } catch (\Exception $e) {
            Log::error('Failed to update gift card information', [
                'admin_id' => $request->user()->id,
                'code_id' => $code->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Update failed']);
        }
    }

    /**
     * Delete gift card
     */
    public function deleteCode(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:v2_gift_card_code,id',
        ]);

        $code = GiftCardCode::find($request->input('id'));
        if (!$code) {
            return $this->fail([404, 'Gift card not found']);
        }

        // Check if already used
        if ($code->status === GiftCardCode::STATUS_USED) {
            return $this->fail([400, 'Gift card has been used and cannot be deleted']);
        }

        try {
            // Check if there are related usage records
            if ($code->usages()->exists()) {
                return $this->fail([400, 'Gift card has usage records and cannot be deleted']);
            }

            $code->delete();
            return $this->success(['message' => 'Deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Failed to delete gift card', [
                'admin_id' => $request->user()->id,
                'code_id' => $code->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Delete failed']);
        }
    }
}
