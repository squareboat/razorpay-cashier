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
        'paused_at',
        'resumed_at',
        'canceled_at',
        'grace_ends_at',
    ];

    protected $dates = ['trial_ends_at', 'paused_at', 'resumed_at', 'canceled_at', 'grace_ends_at'];

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

    public function isPaused()
    {
        return $this->is_paused;
    }

    public function isCanceled()
    {
        return $this->canceled_at !== null;
    }

    public function inGracePeriod()
    {
        return $this->grace_ends_at && now()->isBefore($this->grace_ends_at);
    }
}
