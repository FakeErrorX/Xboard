<?php
namespace App\Http\Routes\V1;

use App\Http\Controllers\V1\Guest\CommController;
use App\Http\Controllers\V1\Guest\PaymentController;
use App\Http\Controllers\V1\Guest\PlanController;
use App\Http\Controllers\V1\Guest\TelegramController;
use App\Http\Controllers\V1\Guest\UddoktaPayController;
use Illuminate\Contracts\Routing\Registrar;

class GuestRoute
{
    public function map(Registrar $router)
    {
        $router->group([
            'prefix' => 'guest'
        ], function ($router) {
            // Plan
            $router->get('/plan/fetch', [PlanController::class, 'fetch']);
            // Telegram
            $router->post('/telegram/webhook', [TelegramController::class, 'webhook']);
            // Payment
            $router->match(['get', 'post'], '/payment/notify/{method}/{uuid}', [PaymentController::class, 'notify']);
            // UddoktaPay
            $router->post('/uddoktapay/webhook', [UddoktaPayController::class, 'webhook']);
            $router->post('/uddoktapay/verify', [UddoktaPayController::class, 'verifyPayment']);
            // UddoktaPay Standard Flow (alternative)
            $router->match(['get', 'post'], '/payment/notify/UddoktaPay/{uuid}', [PaymentController::class, 'notify']);
            // Comm
            $router->get('/comm/config', [CommController::class, 'config']);
        });
    }
}
