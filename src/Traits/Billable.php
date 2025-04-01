<?php

namespace Squareboat\RazorpayCashier\Traits;

use Squareboat\RazorpayCashier\RazorpayCashier;

trait Billable
{
    public function createOrder($amount, $options = [])
    {
        return RazorpayCashier::createOrder($this, $amount, $options);
    }

    public function subscriptions()
    {
        return $this->hasMany(\Squareboat\RazorpayCashier\Models\Subscription::class);
    }
}
