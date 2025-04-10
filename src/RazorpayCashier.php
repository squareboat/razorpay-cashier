<?php

namespace Squareboat\RazorpayCashier;

use Illuminate\Support\Facades\Config;
use Razorpay\Api\Api;

class RazorpayCashier
{
    protected $api;

    /**
     * Number of trial days for subscriptions.
     *
     * @var int|null
     */
    protected $trialDays = null;

    public function __construct()
    {
        $this->api = new Api(Config::get('razorpay.key'), Config::get('razorpay.secret'));
    }

    public static function createOrder($user, $amount, $options = [])
    {
        $instance = new self();
        $order = $instance->api->order->create(array_merge([
            'amount' => $amount,
            'currency' => config('razorpay.currency', 'INR'),
            'receipt' => 'receipt_' . $user->id . '_' . time(),
        ], $options));
        return ['id' => $order->id, 'status' => 'created'];
    }

    public function charge($user, $amount, $options = [])
    {
        $order = $this->api->order->create(array_merge([
            'amount' => $amount,
            'currency' => config('razorpay.currency', 'INR'),
            'receipt' => 'receipt_' . $user->id . '_' . time(),
        ], $options));
        // Note: Payment must be captured via frontend; return order details for now
        return ['id' => $order->id, 'status' => 'created'];
    }

    public function capturePayment($paymentId, $amount)
    {
        $payment = $this->api->payment->fetch($paymentId);
        return $payment->capture(['amount' => $amount]);
    }

    public function createSubscription($planId, $paymentMethodId)
    {
        $plan = $this->api->plan->fetch($planId);
        $amount = $plan->item->amount; // Amount in paise (e.g., 10000 for Rs100)
        $options = [
            'plan_id' => $planId,
            'total_count' => 12,
            'quantity' => 1,
        ];

        if ($this->trialDays) {
            $options['start_at'] = now()->addDays($this->trialDays)->timestamp;
        }

        $subscription = $this->api->subscription->create($options);
        $this->capturePayment($paymentMethodId, $amount); // Use plan's amount
        return [
            'id' => $subscription->id,
            'status' => 'active',
        ];
    }

    public function createCustomer($user)
    {
        return $this->api->customer->create([
            'name' => $user->name,
            'email' => $user->email,
            'contact' => $user->phone ?? '',
            'fail_existing' => 0,
        ]);
    }

    public function syncTrialStatus($subscriptionId)
    {
        $razorpaySubscription = $this->api->subscription->fetch($subscriptionId);
        $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();

        if ($localSubscription && $localSubscription->hasTrialEnded() && $razorpaySubscription->status === 'active') {
            $localSubscription->update(['status' => 'trialed']);
        }
        return $localSubscription;
    }

    public function pauseSubscription($subscriptionId)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if ($subscription->status === 'active') {
            $subscription->pause(); // Razorpay pauses billing
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            if ($localSubscription) {
                $localSubscription->update(['is_paused' => true, 'paused_at' => now()]);
                return true;
            } else {
                logger('Local subscription not found for ID: ' . $subscriptionId);
                return false;
            }
        } else {
            logger('Cannot pause: Status is ' . $subscription->status . '. Subscription must be active.');
            return false;
        }
    }

    public function resumeSubscription($subscriptionId)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if ($subscription->status === 'paused') {
            $subscription->resume(); // Razorpay resumes billing
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            $localSubscription->update(['is_paused' => false, 'resumed_at' => now()]);
            return true;
        }
        return false;
    }

    public function cancelSubscription($subscriptionId, $graceDays = 0)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if (in_array($subscription->status, ['active', 'paused', 'created'])) {
            $subscription->cancel(); // Razorpay cancels subscription
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            $localSubscription->update([
                'canceled_at' => now(),
                'grace_ends_at' => $graceDays > 0 ? now()->addDays($graceDays) : null,
                'status' => 'cancelled',
            ]);
            return true;
        }
        return false;
    }

    public function swapPlan($subscriptionId, $newPlanId)
    {
        $subscription = $this->api->subscription->fetch($subscriptionId);
        if (in_array($subscription->status, ['active', 'paused'])) {
            $newPlan = $this->api->plan->fetch($newPlanId);
            $subscription->update(['plan_id' => $newPlanId]); // Razorpay updates plan
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            $localSubscription->update(['plan_id' => $newPlanId]);
            return true;
        }
        return false;
    }

    protected function getUserFromContext()
    {
        if (function_exists('auth') && auth()->check()) {
            return auth()->user();
        }
        throw new \Exception('User context not available. Use authenticated user or pass user explicitly.');
    }

    protected function createCustomerForSubscription($subscription)
    {
        // Create a customer if not provided, based on subscription data
        $customerData = [
            'name' => 'Default Customer', // Fetch from subscription or user if available
            'email' => 'test112@example.com', // Replace with dynamic logic
            'contact' => '9299999999', // Replace with dynamic logic
        ];
        return $this->api->customer->create($customerData);
    }

    public function createRazorpayInvoice($subscriptionId, $customerId = null, $notes = null, array $lineItems = [])
    {
        try {
            $subscription = $this->api->subscription->fetch($subscriptionId);
            logger('Creating Razorpay invoice for subscription: ' . $subscriptionId);

            $customer = $customerId ? $this->api->customer->fetch($customerId) : $this->createCustomerForSubscription($subscription);

            // Prepare invoice data
            $invoiceData = [
                'type' => 'invoice',
                'customer_id' => $customer->id,
                'subscription_id' => $subscriptionId, // Link to Razorpay subscription
                'description' => $notes ?? 'Invoice for subscription ' . $subscriptionId,
                'sms_notify' => 1,
                'email_notify' => 1,
                'expire_by' => strtotime('+30 days'), // Default expiry
            ];

            // Add line items if provided (optional override)
            if (!empty($lineItems)) {
                $invoiceData['line_items'] = array_map(function ($item) {
                    return [
                        'name' => $item['name'] ?? 'Item',
                        'amount' => (int) ($item['amount'] * 100), // Amount in paise
                        'currency' => $item['currency'] ?? 'INR',
                        'quantity' => $item['quantity'] ?? 1,
                    ];
                }, $lineItems);
            }

            $razorpayInvoice = $this->api->invoice->create($invoiceData);
            logger('Razorpay invoice created: ' . $razorpayInvoice->id);

            // Map Razorpay subscription ID to local subscription ID
            $localSubscription = \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $subscriptionId)->first();
            if (!$localSubscription) {
                throw new \Exception('Local subscription not found for Razorpay ID: ' . $subscriptionId);
            }

            // Store in local database
            $invoiceNumber = 'INV-' . time() . '-' . $subscriptionId;
            $localInvoice = \Squareboat\RazorpayCashier\Models\Invoice::create([
                'subscription_id' => $localSubscription->id, // Use local ID
                'razorpay_invoice_id' => $razorpayInvoice->id,
                'invoice_number' => $invoiceNumber,
                'amount' => $razorpayInvoice->amount / 100, // Convert paise to rupees
                'currency' => $razorpayInvoice->currency,
                'status' => $razorpayInvoice->status,
                'due_date' => date('Y-m-d H:i:s', $razorpayInvoice->expire_by),
                'notes' => $notes,
            ]);

            return [
                'success' => true,
                'message' => 'Razorpay invoice created and synced locally',
                'razorpay_invoice' => $razorpayInvoice->toArray(),
                'local_invoice' => $localInvoice,
            ];
        } catch (\Exception $e) {
            logger('Error creating Razorpay invoice: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create Razorpay invoice: ' . $e->getMessage(),
                'subscription_id' => $subscriptionId,
            ];
        }
    }

    public function getRazorpayInvoice($invoiceId)
    {
        try {
            $razorpayInvoice = $this->api->invoice->fetch($invoiceId);
            logger('Retrieved Razorpay invoice: ' . $razorpayInvoice->id);

            // Sync with local database if not exists
            $localInvoice = \Squareboat\RazorpayCashier\Models\Invoice::firstOrCreate(
                ['razorpay_invoice_id' => $razorpayInvoice->id],
                [
                    'subscription_id' => $razorpayInvoice->subscription_id
                        ? \Squareboat\RazorpayCashier\Models\Subscription::where('razorpay_subscription_id', $razorpayInvoice->subscription_id)->first()->id
                        : null,
                    'invoice_number' => 'INV-' . time() . '-' . $razorpayInvoice->id,
                    'amount' => $razorpayInvoice->amount / 100, // Convert paise to rupees
                    'currency' => $razorpayInvoice->currency,
                    'status' => $razorpayInvoice->status,
                    'due_date' => date('Y-m-d H:i:s', $razorpayInvoice->expire_by),
                    'issued_at' => $razorpayInvoice->date ? date('Y-m-d H:i:s', $razorpayInvoice->date) : null,
                    'paid_at' => $razorpayInvoice->paid_at ? date('Y-m-d H:i:s', $razorpayInvoice->paid_at) : null,
                    'cancelled_at' => $razorpayInvoice->cancelled_at ? date('Y-m-d H:i:s', $razorpayInvoice->cancelled_at) : null,
                    'notes' => $razorpayInvoice->description,
                ]
            );

            return [
                'success' => true,
                'razorpay_invoice' => $razorpayInvoice->toArray(),
                'local_invoice' => $localInvoice,
            ];
        } catch (\Exception $e) {
            logger('Error retrieving Razorpay invoice: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to retrieve Razorpay invoice: ' . $e->getMessage(),
                'invoice_id' => $invoiceId,
            ];
        }
    }

    public function updateRazorpayInvoiceStatus($invoiceId, $action)
    {
        try {
            $razorpayInvoice = $this->api->invoice->fetch($invoiceId);
            $validActions = ['issue', 'cancel'];
            if (!in_array($action, $validActions)) {
                throw new \Exception('Invalid action: ' . $action);
            }

            if ($action === 'issue') {
                $razorpayInvoice->issue();
            } elseif ($action === 'cancel') {
                $razorpayInvoice->cancel();
            }

            logger('Razorpay invoice action performed: ' . $invoiceId . ' - ' . $action);

            // Sync with local database
            $localInvoice = \Squareboat\RazorpayCashier\Models\Invoice::where('razorpay_invoice_id', $invoiceId)->first();
            if ($localInvoice) {
                $localInvoice->update([
                    'status' => $razorpayInvoice->status,
                    'issued_at' => $razorpayInvoice->date ? date('Y-m-d H:i:s', $razorpayInvoice->date) : $localInvoice->issued_at,
                    'paid_at' => $razorpayInvoice->paid_at ? date('Y-m-d H:i:s', $razorpayInvoice->paid_at) : $localInvoice->paid_at,
                    'cancelled_at' => $razorpayInvoice->cancelled_at ? date('Y-m-d H:i:s', $razorpayInvoice->cancelled_at) : $localInvoice->cancelled_at,
                ]);
            }

            return [
                'success' => true,
                'message' => 'Razorpay invoice ' . $action . 'd successfully',
                'razorpay_invoice' => $razorpayInvoice->toArray(),
                'local_invoice' => $localInvoice ?? null,
            ];
        } catch (\Exception $e) {
            logger('Error updating Razorpay invoice: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to update Razorpay invoice: ' . $e->getMessage(),
                'invoice_id' => $invoiceId,
            ];
        }
    }
}
