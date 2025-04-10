<?php

namespace Squareboat\RazorpayCashier\Models;

use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    protected $fillable = [
        'subscription_id',
        'razorpay_invoice_id',
        'invoice_number',
        'amount',
        'currency',
        'status',
        'due_date',
        'issued_at',
        'paid_at',
        'cancelled_at',
        'notes',
    ];

    protected $dates = ['due_date', 'issued_at', 'paid_at', 'cancelled_at'];

    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }
}
