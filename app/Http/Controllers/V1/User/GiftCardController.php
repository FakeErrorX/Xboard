<?php

namespace App\Http\Controllers\V1\User;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\GiftCardCheckRequest;
use App\Http\Requests\User\GiftCardRedeemRequest;
use App\Models\GiftCardUsage;
use App\Services\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class GiftCardController extends Controller
{
    /**
     * Query redemption code information
     */
    public function check(GiftCardCheckRequest $request)
    {
        try {
            $giftCardService = new GiftCardService($request->input('code'));
            $giftCardService->setUser($request->user());

            // 1. Validate if the gift card itself is valid (e.g., does not exist, expired, disabled)
            $giftCardService->validateIsActive();

            // 2. Check if the user meets usage conditions, but don't throw exception here
            $eligibility = $giftCardService->checkUserEligibility();

            // 3. Get card information and reward preview
            $codeInfo = $giftCardService->getCodeInfo();
            $rewardPreview = $giftCardService->previewRewards();

            return $this->success([
                'code_info' => $codeInfo, // Already includes plan_info
                'reward_preview' => $rewardPreview,
                'can_redeem' => $eligibility['can_redeem'],
                'reason' => $eligibility['reason'],
            ]);

        } catch (ApiException $e) {
            // Only catch exceptions thrown by validateIsActive
            return $this->fail([400, $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Gift card query failed', [
                'code' => $request->input('code'),
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);
            return $this->fail([500, 'Query failed, please try again later']);
        }
    }

    /**
     * Use redemption code
     */
    public function redeem(GiftCardRedeemRequest $request)
    {
        try {
            $giftCardService = new GiftCardService($request->input('code'));
            $giftCardService->setUser($request->user());
            $giftCardService->validate();

            // Use gift card
            $result = $giftCardService->redeem([
                // 'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            Log::info('Gift card used successfully', [
                'code' => $request->input('code'),
                'user_id' => $request->user()->id,
                'rewards' => $result['rewards'],
            ]);

            return $this->success([
                'message' => 'Redemption successful!',
                'rewards' => $result['rewards'],
                'invite_rewards' => $result['invite_rewards'],
                'template_name' => $result['template_name'],
            ]);

        } catch (ApiException $e) {
            return $this->fail([400, $e->getMessage()]);
        } catch (\Exception $e) {
            Log::error('Gift card usage failed', [
                'code' => $request->input('code'),
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return $this->fail([500, 'Redemption failed, please try again later']);
        }
    }

    /**
     * Get user redemption records
     */
    public function history(Request $request)
    {
        $request->validate([
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        $perPage = $request->input('per_page', 15);

        $usages = GiftCardUsage::with(['template', 'code'])
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        $data = $usages->getCollection()->map(function (GiftCardUsage $usage) {
            return [
                'id' => $usage->id,
                'code' => ($usage->code instanceof \App\Models\GiftCardCode && $usage->code->code)
                    ? (substr($usage->code->code, 0, 8) . '****')
                    : '',
                'template_name' => $usage->template->name ?? '',
                'template_type' => $usage->template->type ?? '',
                'template_type_name' => $usage->template->type_name ?? '',
                'rewards_given' => $usage->rewards_given,
                'invite_rewards' => $usage->invite_rewards,
                'multiplier_applied' => $usage->multiplier_applied,
                'created_at' => $usage->created_at,
            ];
        })->values();
        return response()->json([
            'data' => $data,
            'pagination' => [
                'current_page' => $usages->currentPage(),
                'last_page' => $usages->lastPage(),
                'per_page' => $usages->perPage(),
                'total' => $usages->total(),
            ],
        ]);
    }

    /**
     * Get redemption record details
     */
    public function detail(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:v2_gift_card_usage,id',
        ]);

        $usage = GiftCardUsage::with(['template', 'code', 'inviteUser'])
            ->where('user_id', $request->user()->id)
            ->where('id', $request->input('id'))
            ->first();

        if (!$usage) {
            return $this->fail([404, 'Record does not exist']);
        }

        return $this->success([
            'id' => $usage->id,
            'code' => $usage->code->code ?? '',
            'template' => [
                'name' => $usage->template->name ?? '',
                'description' => $usage->template->description ?? '',
                'type' => $usage->template->type ?? '',
                'type_name' => $usage->template->type_name ?? '',
                'icon' => $usage->template->icon ?? '',
                'theme_color' => $usage->template->theme_color ?? '',
            ],
            'rewards_given' => $usage->rewards_given,
            'invite_rewards' => $usage->invite_rewards,
            'invite_user' => $usage->inviteUser ? [
                'id' => $usage->inviteUser->id ?? '',
                'email' => isset($usage->inviteUser->email) ? (substr($usage->inviteUser->email, 0, 3) . '***@***') : '',
            ] : null,
            'user_level_at_use' => $usage->user_level_at_use,
            'plan_id_at_use' => $usage->plan_id_at_use,
            'multiplier_applied' => $usage->multiplier_applied,
            // 'ip_address' => $usage->ip_address,
            'notes' => $usage->notes,
            'created_at' => $usage->created_at,
        ]);
    }

    /**
     * Get available gift card types
     */
    public function types(Request $request)
    {
        return $this->success([
            'types' => \App\Models\GiftCardTemplate::getTypeMap(),
        ]);
    }
}
