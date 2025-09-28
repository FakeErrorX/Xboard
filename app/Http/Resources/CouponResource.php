<?php

namespace App\Http\Resources;

use App\Models\Coupon;
use App\Services\CouponService;
use App\Services\PlanService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Coupon resource class
 *
 * @property array|null $limit_plan_ids List of restricted plan IDs
 */
class CouponResource extends JsonResource
{
    /**
     * Transform the resource into an array
     *
     * @param Request $request Request instance
     * @return array<string, mixed> Transformed array
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->resource->toArray(),
            'limit_plan_ids' => empty($this->limit_plan_ids) ? null : collect($this->limit_plan_ids)
                ->map(fn(mixed $id): string => (string) $id)
                ->values()
                ->all(),
            'limit_period' => empty($this->limit_period) ? null : collect($this->limit_period)
                ->map(fn(mixed $period): string => (string) PlanService::convertToLegacyPeriod($period))
                ->values()
                ->all(),
        ];
    }
}
