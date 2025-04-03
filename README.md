
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>
<h1>+</h1>
<p align="center"><a href="https://laravel.com" target="_blank"><img src="src/public/image.png" width="400" alt="Razorpay Logo"></a></p>





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
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<button id="pay-btn">Pay Now</button>

<script>
    document.getElementById('pay-btn').onclick = function() {
        var options = {
            key: "{{ config('razorpay.key') }}",
            amount: 10000, // 100 INR in paise
            currency: "INR",
            name: "Your App Name",
            description: "One-time Charge or Subscription",
            handler: function(response) {
                fetch('/charge-or-subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        payment_method_id: response.razorpay_payment_id
                    })
                })
                .then(res => res.json())
                .then(data => alert(data.message));
            }
        };
        var rzp = new Razorpay(options);
        rzp.open();
    };
</script>
```

Backend Route (e.g., `routes/web.php`):

```php
Route::post('/charge-or-subscribe', function () {
    $user = auth()->user();
    $paymentMethodId = request('payment_method_id');
    // For one-time charge:
    // $user->charge(10000);
    // For subscription:
    $user->newSubscription('default', 'plan_id')->create($paymentMethodId);
    return response()->json(['message' => 'Payment successful']);
});
```

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
