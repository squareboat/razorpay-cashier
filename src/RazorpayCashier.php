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

    public function createCustomer($user)
    {
        return $this->api->customer->create([
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->phone ?? '',
            'fail_existing' => 0,
        ]);
    }

    public function syncTrialStatus($subscriptionId)
    {
        $razorpaySubscription = $this->api->subscription->fetch($subscriptionId);
        $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();

        if ($localSubscription && $localSubscription->hasTrialEnded() && $razorpaySubscription->status === 'active') {
            $localSubscription->update(['status' => 'trialed']);
        }
        return $localSubscription;
    }

    public function pauseSubscription($subscriptionId)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if ($subscription->status === 'active') {
            $subscription->pause(); // Razorpay pauses billing
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            if ($localSubscription) {
                $localSubscription->update(['is_paused' => true, 'paused_at' => now()]);
                return true;
            } else {
                logger('Local subscription not found for ID: ' . $subscriptionId);
                return false;
            }
        } else {
            logger('Cannot pause: Status is ' . $subscription->status . '. Subscription must be active.');
            return false;
        }
    }

    public function resumeSubscription($subscriptionId)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if ($subscription->status === 'paused') {
            $subscription->resume(); // Razorpay resumes billing
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            $localSubscription->update(['is_paused' => false, 'resumed_at' => now()]);
            return true;
        }
        return false;
    }

    public function cancelSubscription($subscriptionId, $graceDays = 0)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if (in_array($subscription->status, ['active', 'paused', 'created'])) {
            $subscription->cancel(); // Razorpay cancels subscription
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            $localSubscription->update([
                'canceled_at' => now(),
                'grace_ends_at' => $graceDays > 0 ? now()->addDays($graceDays) : null,
                'status' => 'cancelled',
            ]);
            return true;
        }
        return false;
    }

    public function swapPlan($subscriptionId, $newPlanId)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if (in_array($subscription->status, ['active', 'paused'])) {
            $newPlan = $this->api->plan->fetch($newPlanId);
            $subscription->update(['plan_id' => $newPlanId]); // Razorpay updates plan
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            $localSubscription->update(['plan_id' => $newPlanId]);
            return true;
        }
        return false;
    }

    protected function getUserFromContext()
    {
        if (function_exists('auth') && auth()->check()) {
            return auth()->user();
        }
        throw new \Exception('User context not available. Use authenticated user or pass user explicitly.');
    }
}
