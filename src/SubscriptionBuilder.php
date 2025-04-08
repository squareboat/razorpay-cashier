<?php

namespace Squareboat\RazorpayCashier;

class SubscriptionBuilder
{
    protected $user;
    protected $name;
    protected $plan;
    protected $trialDays = null;

    public function __construct($user, $name, $plan)
    {
        $this->user = $user;
        $this->name = $name;
        $this->plan = $plan;
    }

    public function create($paymentMethodId)
    {
        $razorpay = new RazorpayCashier();
        $subscriptionData = $razorpay->createSubscription($this->plan, $paymentMethodId);

        $attributes = [
            'name' => $this->name,
            'plan_id' => $this->plan,
            'razorpay_subscription_id' => $subscriptionData['id'],
            'status' => $subscriptionData['status'],
        ];

        if ($this->trialDays) {
            $attributes['trial_ends_at'] = now()->addDays($this->trialDays);
        }

        $subscription = $this->user->subscriptions()->create($attributes);
        return $subscription;
    }

    public function trialDays($days)
    {
        $this->trialDays = $days;
        return $this;
    }
}
