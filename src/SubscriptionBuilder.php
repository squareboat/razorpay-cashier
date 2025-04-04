<?php

namespace Squareboat\RazorpayCashier;

class SubscriptionBuilder
{
    protected $user;
    protected $name;
    protected $plan;

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

        // Save subscription to database
        $subscription = $this->user->subscriptions()->create([
            'name' => $this->name,
            'plan_id' => $this->plan,
            'razorpay_subscription_id' => $subscriptionData['id'],
            'status' => $subscriptionData['status'],
        ]);

        return $subscription;
    }
}
