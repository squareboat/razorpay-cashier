<?php

namespace Squareboat\RazorpayCashier;

use Illuminate\Support\Facades\Config;
use Razorpay\Api\Api;

class RazorpayCashier
{
    protected $api;

    /**
     * Number of trial days for subscriptions.
     *
     * @var int|null
     */
    protected $trialDays = null;

    public function __construct()
    {
        $this->api = new Api(Config::get('razorpay.key'), Config::get('razorpay.secret'));
    }

    public static function createOrder($user, $amount, $options = [])
    {
        $instance = new self();
        $order = $instance->api->order->create(array_merge([
            'amount' => $amount,
            'currency' => config('razorpay.currency', 'INR'),
            'receipt' => 'receipt_' . $user->id . '_' . time(),
        ], $options));
        return ['id' => $order->id, 'status' => 'created'];
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
        $plan = $this->api->plan->fetch($planId);
        $amount = $plan->item->amount; // Amount in paise (e.g., 10000 for Rs100)
        $options = [
            'plan_id' => $planId,
            'total_count' => 12,
            'quantity' => 1,
        ];

        if ($this->trialDays) {
            $options['start_at'] = now()->addDays($this->trialDays)->timestamp;
        }

        $subscription = $this->api->subscription->create($options);
        $this->capturePayment($paymentMethodId, $amount); // Use plan's amount
        return [
            'id' => $subscription->id,
            'status' => 'active',
        ];
    }
}
