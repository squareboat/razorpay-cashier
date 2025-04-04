<?php

namespace Squareboat\RazorpayCashier;

use Illuminate\Support\Facades\Config;
use Razorpay\Api\Api;

class RazorpayCashier
{
    protected $api;

    public function __construct()
    {
        $this->api = new Api(Config::get('razorpay.key'), Config::get('razorpay.secret'));
    }

    public static function createOrder($billable, $amount, $options = [])
    {
        $instance = new self();
        $order = $instance->api->order->create([
            'amount' => $amount * 100, // Razorpay uses paisa
            'currency' => config('razorpay.currency'),
            'receipt' => 'order_' . uniqid(),
        ]);

        return $order;
    }

    public function charge($user, $amount, $options = [])
    {
        $order = $this->api->order->create(array_merge([
            'amount' => $amount,
            'currency' => config('razorpay.currency', 'INR'),
            'receipt' => 'receipt_' . $user->id . '_' . time(),
        ], $options));
        // Note: Payment must be captured via frontend; return order details for now
        return ['id' => $order->id, 'status' => 'created'];
    }

    public function capturePayment($paymentId, $amount)
    {
        $payment = $this->api->payment->fetch($paymentId);
        return $payment->capture(['amount' => $amount]);
    }

    public function createSubscription($planId, $paymentMethodId)
    {
        $subscription = $this->api->subscription->create([
            'plan_id' => $planId,
            'total_count' => 12, // Example: 12 billing cycles
            'quantity' => 1,
        ]);

        // Capture the initial payment
        $this->capturePayment($paymentMethodId, 10000); // Adjust amount dynamically in future

        return [
            'id' => $subscription->id,
            'status' => 'active',
        ];
    }
}
