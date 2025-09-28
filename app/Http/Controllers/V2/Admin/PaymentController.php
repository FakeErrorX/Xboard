<?php

namespace App\Http\Controllers\V2\Admin;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Utils\Helper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function getPaymentMethods()
    {
        $methods = [];

        $pluginMethods = PaymentService::getAllPaymentMethodNames();
        $methods = array_merge($methods, $pluginMethods);

        return $this->success(array_unique($methods));
    }

    public function fetch()
    {
        $payments = Payment::orderBy('sort', 'ASC')->get();
        foreach ($payments as $k => $v) {
            $notifyUrl = url("/api/v1/guest/payment/notify/{$v->payment}/{$v->uuid}");
            if ($v->notify_domain) {
                $parseUrl = parse_url($notifyUrl);
                $notifyUrl = $v->notify_domain . $parseUrl['path'];
            }
            $payments[$k]['notify_url'] = $notifyUrl;
        }
        return $this->success($payments);
    }

    public function getPaymentForm(Request $request)
    {
        try {
            $paymentService = new PaymentService($request->input('payment'), $request->input('id'));
            return $this->success(collect($paymentService->form()));
        } catch (\Exception $e) {
            return $this->fail([400, 'Payment method does not exist or is not enabled']);
        }
    }

    public function show(Request $request)
    {
        $payment = Payment::find($request->input('id'));
        if (!$payment)
            return $this->fail([400202, 'Payment method does not exist']);
        $payment->enable = !$payment->enable;
        if (!$payment->save())
            return $this->fail([500, 'Save failed']);
        return $this->success(true);
    }

    public function save(Request $request)
    {
        if (!admin_setting('app_url')) {
            return $this->fail([400, 'Please configure site address in site configuration']);
        }
        $params = $request->validate([
            'name' => 'required',
            'icon' => 'nullable',
            'payment' => 'required',
            'config' => 'required',
            'notify_domain' => 'nullable|url',
            'handling_fee_fixed' => 'nullable|integer',
            'handling_fee_percent' => 'nullable|numeric|between:0,100'
        ], [
            'name.required' => 'Display name cannot be empty',
            'payment.required' => 'Gateway parameter cannot be empty',
            'config.required' => 'Configuration parameter cannot be empty',
            'notify_domain.url' => 'Custom notification domain format is incorrect',
            'handling_fee_fixed.integer' => 'Fixed handling fee format is incorrect',
            'handling_fee_percent.between' => 'Percentage handling fee range must be between 0-100'
        ]);
        if ($request->input('id')) {
            $payment = Payment::find($request->input('id'));
            if (!$payment)
                return $this->fail([400202, 'Payment method does not exist']);
            try {
                $payment->update($params);
            } catch (\Exception $e) {
                Log::error($e);
                return $this->fail([500, 'Save failed']);
            }
            return $this->success(true);
        }
        $params['uuid'] = Helper::randomChar(8);
        if (!Payment::create($params)) {
            return $this->fail([500, 'Save failed']);
        }
        return $this->success(true);
    }

    public function drop(Request $request)
    {
        $payment = Payment::find($request->input('id'));
        if (!$payment)
            return $this->fail([400202, 'Payment method does not exist']);
        return $this->success($payment->delete());
    }


    public function sort(Request $request)
    {
        $request->validate([
            'ids' => 'required|array'
        ], [
            'ids.required' => 'Invalid parameters',
            'ids.array' => 'Invalid parameters'
        ]);
        try {
            DB::beginTransaction();
            foreach ($request->input('ids') as $k => $v) {
                if (!Payment::find($v)->update(['sort' => $k + 1])) {
                    throw new \Exception();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->fail([500, 'Save failed']);
        }

        return $this->success(true);
    }
}
