<?php

namespace Squareboat\RazorpayCashier\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'plan_id',
        'razorpay_subscription_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
