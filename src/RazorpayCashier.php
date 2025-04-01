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
}
