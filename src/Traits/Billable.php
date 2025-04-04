<?php

namespace Squareboat\RazorpayCashier\Traits;

use Squareboat\RazorpayCashier\RazorpayCashier;
use Squareboat\RazorpayCashier\SubscriptionBuilder;

trait Billable
{
    public function createOrder($amount, $options = [])
    {
        return RazorpayCashier::createOrder($this, $amount, $options);
    }

    public function charge($amount, $options = [])
    {
        $razorpay = new RazorpayCashier();
        return $razorpay->charge($this, $amount, $options);
    }

    public function newSubscription($name, $plan)
    {
        return new SubscriptionBuilder($this, $name, $plan);
    }

    public function subscription($name = 'default')
    {
        return new SubscriptionManager($this, $name);
    }

    public function subscriptions()
    {
        return $this->hasMany(\Squareboat\RazorpayCashier\Models\Subscription::class);
    }
}
