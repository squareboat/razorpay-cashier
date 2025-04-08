<?php

namespace Squareboat\RazorpayCashier\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'plan_id',
        'razorpay_subscription_id',
        'status',
        'trial_ends_at',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function onTrial(): bool
    {
        return $this->trial_ends_at && now()->isBefore($this->trial_ends_at);
    }

    public function active()
    {
        return $this->status === 'active' || $this->onTrial();
    }

    public function hasTrialEnded()
    {
        return $this->trial_ends_at && now()->isAfter($this->trial_ends_at);
    }

    public function endTrial()
    {
        if ($this->hasTrialEnded() && $this->status === 'active') {
            $this->update(['status' => 'trialed', 'trial_ends_at' => null]);
            return true;
        }
        return false;
    }
}
