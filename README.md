<p align="center"><a href="https://laravel.com" target="_blank"><img src="src/public/razorpay_and_laravel-removebg-preview.png" width="400" alt="Laravel Logo"></a></p>

# Razorpay Cashier for Laravel

A Laravel package that provides an expressive, fluent interface to integrate [Razorpay](https://razorpay.com/) payment gateway, inspired by Laravel Cashier. This package simplifies handling one-time charges and subscriptions for Laravel applications, tailored for the Indian market with support for INR and 100+ payment methods.

## Features

- Seamless integration with Razorpay's payment gateway
- Fluent API for charging users and managing subscriptions
- Supports Razorpay's extensive payment methods (cards, UPI, netbanking, wallets)
- Compatible with Laravel 12+

## Installation

### Step 1: Install via Composer

```bash
composer require squareboat/razorpay-cashier
```

### Step 2: Publish Configuration

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --tag=razorpay-config
```

This creates `config/razorpay.php` in your project.

### Step 3: Configure Environment

Add your Razorpay API keys to your `.env` file. You can get these from the Razorpay Dashboard:

```env
RAZORPAY_KEY=rzp_test_xxxxxxxxxxxxxx
RAZORPAY_SECRET=xxxxxxxxxxxxxxxx
```

### Step 4: Run Migrations

If your application uses subscriptions, run the migration to create the necessary tables:

```bash
php artisan migrate
```

This sets up a subscriptions table to track user subscriptions.

## Configuration

The `config/razorpay.php` file contains default settings:

```php
return [
    'key' => env('RAZORPAY_KEY'),
    'secret' => env('RAZORPAY_SECRET'),
    'currency' => 'INR',
];
```

Adjust these as needed (e.g., change currency for multi-currency support, if implemented).

## Usage

### Adding the Billable Trait

Add the Billable trait to your User model (or any model you want to bill):

```php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Squareboat\RazorpayCashier\Traits\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

### One-Time Charges

Charge a user a specific amount (e.g., 100 INR):

```php
$user = auth()->user();
$payment = $user->charge(10000); // Amount in paise (100 INR = 10000 paise)
```

Frontend: Use Razorpay's checkout.js to collect payment details.

### Creating Subscriptions

Subscribe a user to a Razorpay plan:

```php
$user = auth()->user();
$subscription = $user->newSubscription('default', 'plan_id_from_razorpay')
    ->create($paymentMethodId);
```

Replace `plan_id_from_razorpay` with a plan ID created in your Razorpay Dashboard.
`$paymentMethodId` is the `razorpay_payment_id` returned from the frontend.

### Frontend Integration

Use Razorpay's checkout.js to initiate payments:

```html
    <button id="charge-btn">Pay 100 INR</button>
    <button id="subscribe-btn">Subscribe (100 INR)</button>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        function pay(amount, endpoint) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (!csrfToken) {
                console.error('CSRF token not found');
                return;
            }
            var options = {
                key: "{{ config('razorpay.key') }}",
                amount: amount,
                currency: "INR",
                name: "Razorpay charge",
                description: endpoint === '/charge' ? 'One-time Charge' : 'Subscription',
                handler: function(response) {
                    console.log('Razorpay response:', response); // Log Razorpay response

                    fetch(endpoint, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                payment_method_id: response.razorpay_payment_id
                            })
                        })
                        .then(res => {
                            console.log('Raw response:', res);
                            return res.json();
                        })
                        .then(data => {
                            console.log('Parsed JSON:', data);
                        })
                        .catch(error => {
                            console.error('Fetch error:', error);
                        });
                }

            };
            var rzp = new Razorpay(options);
            rzp.open();
        }


        document.getElementById('charge-btn').onclick = function() {
            pay({{ $charge_amount }}, '/charge');
        };
        document.getElementById('subscribe-btn').onclick = function() {
            pay({{ $plan_amount }}, '/subscribe');
        };
    </script>
```

Backend Route (e.g., `routes/web.php`):

```php
Route::get('/test-payment', function () {
    $razorpayApi = new \Razorpay\Api\Api(env('RAZORPAY_KEY'), env('RAZORPAY_SECRET'));
    $plan = $razorpayApi->plan->fetch('plan_QEv23lVNekinmU');
    return view('payment', [
        'plan_amount' => $plan->item->amount, // Access amount from item
        'charge_amount' => 10000,
    ]);
});

Route::post('/charge', function (Request $request) {
    $user = auth()->user();

    $paymentMethodId = $request->input('payment_method_id');
    if (!$paymentMethodId) {
        return response()->json(['error' => 'Payment method ID missing'], 400);
    }

    $razorpay = new \Squareboat\RazorpayCashier\RazorpayCashier();
    $payment = $razorpay->capturePayment($paymentMethodId, 10000); // 100 INR
    return response()->json(['message' => 'Payment successful', 'payment' => $payment]);
});

Route::post('/subscribe', function (Request $request) {

    $user = auth()->user();

    $paymentMethodId = $request->input('payment_method_id');
    if (!$paymentMethodId) {
        return response()->json(['error' => 'Payment method ID missing'], 400);
    }

    // Replace 'plan_xxxxxxxxxx' with a real Razorpay plan ID from your dashboard
    $subscription = $user->newSubscription('default', 'plan_QEv23lVNekinmU')
        ->trialDays(7) // 7-day trial
        ->create($paymentMethodId);

    return response()->json(['message' => 'Subscription created with trial', 'subscription' => $subscription]);
});
```

## Trial Periods

The package supports trial periods for subscriptions, allowing users to access a plan for a specified number of days before billing begins. This feature integrates with Razorpay's `start_at` parameter to delay the initial payment and uses a local `trial_ends_at` column in the `subscriptions` table to track the trial end date.

### Setup

1. **Migration**:
   - Ensure the `subscriptions` table includes the `trial_ends_at` column. The package provides a migration to add this column:
     ```bash
     php artisan migrate
     ```
   - The migration (2025_04_08_000001_add_trial_ends_at_to_subscriptions.php) adds a nullable trial_ends_at timestamp. If you haven't run it yet, apply it with the above command.

2. **Model**:
   - The Subscription model (Squareboat\RazorpayCashier\Models\Subscription) is pre-configured to handle trial_ends_at as a date field and includes helper methods to check trial status.

### Usage

To create a subscription with a trial period, use the trialDays() method on the SubscriptionBuilder instance returned by newSubscription():

```php
use App\Models\User;

$user = User::firstOrCreate([
    'email' => 'test@example.com',
    'name' => 'Test User',
    'password' => bcrypt('password'),
]);

$subscription = $user->newSubscription('default', 'plan_xxxxxxxxxx')
    ->trialDays(7) // Set a 7-day trial
    ->create($paymentMethodId);

return response()->json(['message' => 'Subscription created with trial', 'subscription' => $subscription]);
```

- `trialDays($days)`: Specifies the number of days for the trial period. If omitted, the subscription starts immediately with no trial.

**Behavior**:
- During the trial, no payment is captured. The `start_at` parameter is set to `now() + $trialDays`, delaying Razorpay's first billing cycle.
- The `trial_ends_at` column is set to `now() + $trialDays` in the local database.
- After the trial, Razorpay automatically charges the subscription amount based on the plan's interval (e.g., monthly) if the payment method is valid.

### Checking Trial Status

The Subscription model provides methods to check the trial status:

- `onTrial()`: Returns true if the current date is before trial_ends_at.
- `hasTrialEnded()`: Returns true if the current date is after trial_ends_at.
- `endTrial()`: Updates the subscription status to trialed and clears trial_ends_at if the trial has ended (optional manual action).

Example:

```php
$subscription = $user->subscriptions()->first();
if ($subscription->hasTrialEnded()) {
    $subscription->endTrial();
    logger('Trial has ended for subscription: ' . $subscription->id);
}
```

### Syncing with Razorpay

To keep the local subscription status in sync with Razorpay after the trial, use the syncTrialStatus method:

```php
Route::get('/test-trial-status/{subscriptionId}', function ($subscriptionId) {
    $razorpay = new \Squareboat\RazorpayCashier\RazorpayCashier();
    $subscription = $razorpay->syncTrialStatus($subscriptionId);
    return response()->json(['message' => 'Trial status synced', 'subscription' => $subscription]);
});
```

### Notes

- Replace 'plan_xxxxxxxxxx' with your actual Razorpay plan ID from the dashboard.
- Ensure the payment method ($paymentMethodId) is valid for the initial capture after the trial.
- For production, consider integrating webhooks to handle trial end events automatically (see Webhook Handling section when implemented).

### Troubleshooting

- **Trial Not Starting**: Verify trialDays() is called before create().
- **No Billing After Trial**: Ensure start_at is correctly set and the payment method is active.
- **Errors**: Check logs (storage/logs/laravel.log) for Razorpay API responses or database issues.

## Subscription Management

The `squareboat/razorpay-cashier` package provides methods to manage subscriptions, including pausing, resuming, canceling, and swapping plans.

### Usage

Use the following methods on a `Billable` model (e.g., `User`):

- **`pauseSubscription($subscriptionId)`**: Pauses billing for the subscription.
  ```php
  $user->pauseSubscription('sub_QGXpowM0Ewrq0');
  ```
  - Endpoint: POST /pause-subscription/{subscriptionId}
  - Note: Replace sub_QGXpowM0Ewrq0 with a valid Razorpay subscription ID.

- **`resumeSubscription($subscriptionId)`**: Resumes billing for a paused subscription.
  ```php
  $user->resumeSubscription('sub_QGXpowM0Ewrq0');
  ```
  - Endpoint: POST /resume-subscription/{subscriptionId}

- **`cancelSubscription($subscriptionId, $graceDays = 0)`**: Cancels the subscription with an optional grace period.
  ```php
  $user->cancelSubscription('sub_QGXpowM0Ewrq0', 7); // 7-day grace period
  ```
  - Endpoint: POST /cancel-subscription/{subscriptionId}

- **`swapPlan($subscriptionId, $newPlanId)`**: Changes the subscription to a new plan.
  ```php
  $user->swapPlan('sub_QGXpowM0Ewrq0', 'plan_yyyyyyyyyy');
  ```
  - Endpoint: POST /swap-plan/{subscriptionId}/{newPlanId}
  - Response:
    - Success: `{"success": true, "message": "Plan swapped successfully", "subscription_id": "...", "new_plan_id": "..."}`
    - Failure: `{"success": false, "message": "Swap failed: [reason]", "subscription_id": "..."}` (e.g., invalid status, local record not found, or API error).

### Notes

- Replace `sub_QGXpowM0Ewrq0` and `plan_yyyyyyyyyy` with actual Razorpay subscription and plan IDs.
- Ensure the subscription is in an active or paused state for swapping (check via `$subscription->status`).
- Grace periods are stored in the `grace_ends_at` column and respected locally, but Razorpay handles the actual cancellation.
- For API testing (e.g., Postman), the routes use a custom `bypass.csrf` middleware to skip CSRF verification, registered in `bootstrap/app.php`.

### Troubleshooting

- **419 Error**: Ensure the `bypass.csrf` middleware is registered and applied. Clear cache with `php artisan config:clear`.
- **Swap Fails**: Check the response message and logs (`storage/logs/laravel.log`) for details (e.g., status, API errors).
- **Database Mismatch**: Sync with Razorpay using `syncTrialStatus` if needed.
- **Errors**: Review `storage/logs/laravel.log` for API or database issues.

## Invoicing

The `squareboat/razorpay-cashier` package integrates with Razorpay's official invoicing feature and syncs details to a local invoices table for hybrid management.

### Setup

- Run the migration to create the invoices table:
  ```bash
  php artisan migrate
  ```
- Ensure you have configured Razorpay API keys in your .env:
  ```env
  RAZORPAY_KEY=your_razorpay_key_id
  RAZORPAY_SECRET=your_razorpay_secret
  ```
- Enable invoicing in your Razorpay Dashboard under **Invoicing**.

### Usage

Use the following methods on a `Billable` model (e.g., `User`):

- **`createRazorpayInvoice($subscriptionId, $customerId = null, $notes = null, array $lineItems = [])`**: Creates a Razorpay invoice linked to a subscription and syncs it locally.
  ```php
  $user->createRazorpayInvoice('sub_QGXpowM0Ewrq0', null, 'First invoice', [
      ['name' => 'Subscription Fee', 'amount' => 1000, 'quantity' => 1]
  ]);
  ```
  - **Endpoint**: POST /create-razorpay-invoice/{subscriptionId}
  - **Response**: `{"success": true, "message": "Razorpay invoice created and synced locally", "razorpay_invoice": {...}, "local_invoice": {...}}` or failure details.
  - **Note**: Amount is derived from the subscription plan unless lineItems are provided (amount in rupees, converted to paise). Provide a customerId if available.

- **`getRazorpayInvoice($invoiceId)`**: Retrieves a Razorpay invoice and syncs with the local record.
  ```php
  $user->getRazorpayInvoice('inv_1abcde');
  ```
  - **Endpoint**: GET /get-razorpay-invoice/{invoiceId}
  - **Response**: `{"success": true, "razorpay_invoice": {...}, "local_invoice": {...}}` or failure details.

- **`updateRazorpayInvoiceStatus($invoiceId, $action)`**: Updates the Razorpay invoice status and syncs locally (e.g., issue, cancel).
  ```php
  $user->updateRazorpayInvoiceStatus('inv_1abcde', 'issue');
  ```
  - **Endpoint**: POST /update-razorpay-invoice-status/{invoiceId}/{action}
  - **Response**: `{"success": true, "message": "Razorpay invoice issued successfully", "razorpay_invoice": {...}, "local_invoice": {...}}` or failure details.

### Notes

- Replace `sub_QGXpowM0Ewrq0` and `inv_1abcde` with valid Razorpay subscription and invoice IDs.
- Currency defaults to INR; contact Razorpay for international support.
- Local invoices table syncs with Razorpay data for offline access or custom reporting.
- For testing, use the `bypass.csrf` middleware (registered in `bootstrap/app.php`).

### Troubleshooting

- **Failure Responses**: Check the message field and logs (`storage/logs/laravel.log`) for details (e.g., invalid subscription).
- **API Errors**: Ensure API keys are correct and invoicing is enabled in Razorpay.
- **Database Sync**: Verify the invoices table is populated and subscription IDs are valid.

## Upcoming Features

1. **Webhook Handling:**
   - Process Razorpay webhook events (e.g., `subscription.charged`, `payment.failed`, `subscription.cancelled`).
   - Store events in a `razorpay_events` table for idempotency and debugging.

2. **Payment Method Management:**
   - Store and manage customer payment methods (e.g., cards) using Razorpay's Customer API.
   - Allow updating or deleting payment methods.

3. **Multi-Currency Support:**
   - Support payments and subscriptions in multiple currencies (e.g., INR, USD).
   - Configure currency dynamically via options or config.

4. **Retries for Failed Payments:**
   - Automatically retry failed subscription charges with configurable rules.
   - Queue retries using Laravel's job system.

5. **Grace Periods:**
   - Allow a grace period after subscription cancellation or payment failure before deactivating services.
   - Track `ends_at` for grace period logic.

6. **Coupons/Discounts:**
   - Apply Razorpay coupons to subscriptions or one-time charges.
   - Store discount details locally.

7. **Refunds:**
    - Process refunds for one-time charges or subscription payments.
    - Update local records accordingly.

8. **Subscription Quantity:**
    - Support variable quantities for subscriptions (e.g., 5 users on a plan).
    - Adjust billing via Razorpay's `quantity` parameter.

9. **Tax Handling:**
    - Apply taxes to charges and subscriptions using Razorpay's tax features.
    - Store tax details in invoices.

10. **Customer Management:**
    - Link Razorpay customers to Laravel users for recurring payments.
    - Sync customer data (e.g., email, phone) with Razorpay.

11. **Payment Receipts:**
    - Send email receipts for successful payments via Razorpay's email system or Laravel's mail.

12. **Frontend Integration Enhancements:**
    - Add prebuilt Blade components or JavaScript helpers for easier checkout integration.

## Testing

Use Razorpay's test mode with these sample card details:

- Card: 4111 1111 1111 1111
- Expiry: Any future date
- CVV: 123
- OTP: 123456 (if prompted)

Check the Razorpay Test Cards for more options.

## Requirements

- PHP 8.2 or higher
- Laravel 12.0 or higher
- Razorpay PHP SDK 2.8 or higher

## Troubleshooting

- **Payment Fails**: Verify API keys and test mode settings in the Razorpay Dashboard.
- **Class Not Found**: Run `composer dump-autoload` and ensure the provider is in `bootstrap/providers.php`.

## Contributing

Feel free to submit issues or pull requests on GitHub.

## License

This package is open-sourced software.

## Support

For questions or issues, open a ticket on the GitHub Issues page.
