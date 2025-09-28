<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CouponGenerate;
use App\Http\Requests\Admin\CouponSave;
use App\Models\Coupon;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CouponController extends Controller
{
    private function applyFiltersAndSorts(Request $request, $builder)
    {
        if ($request->has('filter')) {
            collect($request->input('filter'))->each(function ($filter) use ($builder) {
                $key = $filter['id'];
                $value = $filter['value'];
                $builder->where(function ($query) use ($key, $value) {
                    if (is_array($value)) {
                        $query->whereIn($key, $value);
                    } else {
                        $query->where($key, 'like', "%{$value}%");
                    }
                });
            });
        }

        if ($request->has('sort')) {
            collect($request->input('sort'))->each(function ($sort) use ($builder) {
                $key = $sort['id'];
                $value = $sort['desc'] ? 'DESC' : 'ASC';
                $builder->orderBy($key, $value);
            });
        }
    }
    public function fetch(Request $request)
    {
        $current = $request->input('current', 1);
        $pageSize = $request->input('pageSize', 10);
        $builder = Coupon::query();
        $this->applyFiltersAndSorts($request, $builder);
        $coupons = $builder
            ->orderBy('created_at', 'desc')
            ->paginate($pageSize, ["*"], 'page', $current);
        return $this->paginate($coupons);
    }

    public function update(Request $request)
    {
        $params = $request->validate([
            'id' => 'required|numeric',
            'show' => 'nullable|boolean'
        ], [
            'id.required' => 'Coupon ID cannot be empty',
            'id.numeric' => 'Coupon ID must be numeric'
        ]);
        try {
            DB::beginTransaction();
            $coupon = Coupon::find($request->input('id'));
            if (!$coupon) {
                throw new ApiException(400201, 'Coupon does not exist');
            }
            $coupon->update($params);
            DB::commit();
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500, 'Save failed']);
        }
    }

    public function show(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => 'Coupon ID cannot be empty',
            'id.numeric' => 'Coupon ID must be numeric'
        ]);
        $coupon = Coupon::find($request->input('id'));
        if (!$coupon) {
            return $this->fail([400202, 'Coupon does not exist']);
        }
        $coupon->show = !$coupon->show;
        if (!$coupon->save()) {
            return $this->fail([500, 'Save failed']);
        }
        return $this->success(true);
    }

    public function generate(CouponGenerate $request)
    {
        if ($request->input('generate_count')) {
            $this->multiGenerate($request);
            return;
        }

        $params = $request->validated();
        if (!$request->input('id')) {
            if (!isset($params['code'])) {
                $params['code'] = Helper::randomChar(8);
            }
            if (!Coupon::create($params)) {
                return $this->fail([500, 'Create failed']);
            }
        } else {
            try {
                Coupon::find($request->input('id'))->update($params);
            } catch (\Exception $e) {
                \Log::error($e);
                return $this->fail([500, 'Save failed']);
            }
        }

        return $this->success(true);
    }

    private function multiGenerate(CouponGenerate $request)
    {
        $coupons = [];
        $coupon = $request->validated();
        $coupon['created_at'] = $coupon['updated_at'] = time();
        $coupon['show'] = 1;
        unset($coupon['generate_count']);
        for ($i = 0; $i < $request->input('generate_count'); $i++) {
            $coupon['code'] = Helper::randomChar(8);
            array_push($coupons, $coupon);
        }
        try {
            DB::beginTransaction();
            if (
                !Coupon::insert(array_map(function ($item) use ($coupon) {
                    // format data
                    if (isset($item['limit_plan_ids']) && is_array($item['limit_plan_ids'])) {
                        $item['limit_plan_ids'] = json_encode($coupon['limit_plan_ids']);
                    }
                    if (isset($item['limit_period']) && is_array($item['limit_period'])) {
                        $item['limit_period'] = json_encode($coupon['limit_period']);
                    }
                    return $item;
                }, $coupons))
            ) {
                throw new \Exception();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, 'Generation failed']);
        }

        $data = "Name,Type,Amount or Percentage,Start Time,End Time,Usage Limit,Available for Subscription,Coupon Code,Created Time\r\n";
        foreach ($coupons as $coupon) {
            $type = ['', 'Amount', 'Percentage'][$coupon['type']];
            $value = ['', ($coupon['value'] / 100), $coupon['value']][$coupon['type']];
            $startTime = date('Y-m-d H:i:s', $coupon['started_at']);
            $endTime = date('Y-m-d H:i:s', $coupon['ended_at']);
            $limitUse = $coupon['limit_use'] ?? 'Unlimited';
            $createTime = date('Y-m-d H:i:s', $coupon['created_at']);
            $limitPlanIds = isset($coupon['limit_plan_ids']) ? implode("/", $coupon['limit_plan_ids']) : 'Unlimited';
            $data .= "{$coupon['name']},{$type},{$value},{$startTime},{$endTime},{$limitUse},{$limitPlanIds},{$coupon['code']},{$createTime}\r\n";
        }
        echo $data;
    }

    public function drop(Request $request)
    {
        $request->validate([
            'id' => 'required|numeric'
        ], [
            'id.required' => 'Coupon ID cannot be empty',
            'id.numeric' => 'Coupon ID must be numeric'
        ]);
        $coupon = Coupon::find($request->input('id'));
        if (!$coupon) {
            return $this->fail([400202, 'Coupon does not exist']);
        }
        if (!$coupon->delete()) {
            return $this->fail([500, 'Delete failed']);
        }

        return $this->success(true);
    }
}
