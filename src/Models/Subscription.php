<?php

namespace Squareboat\RazorpayCashier\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'razorpay_subscription_id',
        'plan_id',
        'status',
    ];
}
