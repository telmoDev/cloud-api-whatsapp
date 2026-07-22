# Laravel WhatsApp Cloud API Client

[![Latest Version on Packagist](https://img.shields.io/packagist/v/telmodev/cloud-api-whatsapp.svg?style=flat-square)](https://packagist.org/packages/telmodev/cloud-api-whatsapp)
[![Total Downloads](https://img.shields.io/packagist/dt/telmodev/cloud-api-whatsapp.svg?style=flat-square)](https://packagist.org/packages/telmodev/cloud-api-whatsapp)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)

A simple, clean, and elegant Laravel package to interact with the Meta WhatsApp Cloud API.

## Features

- ⚡️ Seamless integration with Laravel's HTTP Client (`Http::`) and facades
- 📱 Dynamic configuration — swap token or phone number ID on the fly (multi-tenant support)
- ✉️ Text messages, template messages, replies, and emoji reactions
- 🔘 Interactive messages — reply buttons and list menus
- 🖼️ Media — images, videos, audio, documents, stickers (upload, send, delete)
- 📍 Location sharing and contact cards
- 🏢 Business Profile management (read and update)
- 📋 Template management — list, create, and delete message templates
- 🔔 Webhook handling — challenge verification and HMAC-SHA256 signature validation
- 🧪 Fully testable with Laravel's HTTP mocking

## Requirements

- PHP 8.2 or higher
- Laravel 10, 11, or 12

## Installation

Install the package via Composer:

```bash
composer require telmodev/cloud-api-whatsapp
```

The Service Provider and Facade are registered automatically via Laravel's package auto-discovery. No manual changes to `config/app.php` are needed.

### Publish the configuration file

```bash
php artisan vendor:publish --tag="cloud-api-whatsapp-config"
```

This creates `config/cloud-api-whatsapp.php` in your project with all available options.

## Configuration

Add these variables to your `.env` file:

```env
WHATSAPP_TOKEN="your-meta-system-user-access-token"
WHATSAPP_PHONE_NUMBER_ID="your-phone-number-id"
WHATSAPP_BUSINESS_ACCOUNT_ID="your-whatsapp-business-account-id"
WHATSAPP_API_VERSION="v20.0"
WHATSAPP_TIMEOUT=30
```

`WHATSAPP_BUSINESS_ACCOUNT_ID` is required for template management endpoints. All other template and messaging endpoints only need `WHATSAPP_PHONE_NUMBER_ID`.

---

## Usage

All examples use the `CloudApiWhatsapp` facade. You can also resolve the class via dependency injection.

```php
use Telmo\CloudApiWhatsapp\Facades\CloudApiWhatsapp;
```

---

### Text Messages

```php
// Simple text
CloudApiWhatsapp::sendMessage('+1234567890', 'Hello from Laravel!');

// With link preview
CloudApiWhatsapp::sendMessage('+1234567890', 'Check this: https://laravel.com', [
    'preview_url' => true,
]);
```

### Reply to a Message

Quote a previous message in the conversation thread.

```php
CloudApiWhatsapp::replyToMessage(
    to: '+1234567890',
    body: 'Got your message, we will look into it!',
    replyMessageId: 'wamid.HBgLMTIzNDU2Nzg5MA=='
);
```

### Emoji Reactions

React to a received message. Pass an empty string to remove an existing reaction.

```php
// Add a reaction
CloudApiWhatsapp::sendReaction('wamid.HBgLMTIzNDU2Nzg5MA==', '👍');

// Remove a reaction
CloudApiWhatsapp::sendReaction('wamid.HBgLMTIzNDU2Nzg5MA==', '');
```

### Template Messages

Required for business-initiated conversations (outside the 24-hour customer window).

```php
CloudApiWhatsapp::sendTemplate(
    to: '+1234567890',
    templateName: 'order_confirmation',
    languageCode: 'en_US',
    components: [
        [
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => 'ORD-98765'],
                ['type' => 'text', 'text' => '$49.99'],
            ],
        ],
    ]
);
```

---

### Interactive Messages

#### Reply Buttons (up to 3)

```php
CloudApiWhatsapp::sendButtons(
    to: '+1234567890',
    body: 'Would you like to confirm your appointment?',
    buttons: [
        ['id' => 'confirm', 'title' => 'Yes, confirm'],
        ['id' => 'cancel',  'title' => 'No, cancel'],
    ],
    header: 'Appointment Reminder',  // optional
    footer: 'Reply anytime'          // optional
);
```

#### List Menu

```php
CloudApiWhatsapp::sendList(
    to: '+1234567890',
    body: 'Please select a support category',
    buttonLabel: 'View categories',
    sections: [
        [
            'title' => 'Technical',
            'rows' => [
                ['id' => 'cat_billing',  'title' => 'Billing',       'description' => 'Invoices and payments'],
                ['id' => 'cat_account',  'title' => 'My Account',    'description' => 'Login, password, profile'],
            ],
        ],
        [
            'title' => 'General',
            'rows' => [
                ['id' => 'cat_other', 'title' => 'Other', 'description' => 'Anything else'],
            ],
        ],
    ],
    header: 'Support',   // optional
    footer: 'We\'re here to help'  // optional
);
```

---

### Media

You can send media using a public URL or a Meta Media ID obtained after uploading.

#### Images

```php
// By URL
CloudApiWhatsapp::sendImage('+1234567890', 'https://example.com/banner.png', 'Summer sale!');

// By Media ID
CloudApiWhatsapp::sendImage('+1234567890', 'your-media-id');
```

#### Documents

```php
CloudApiWhatsapp::sendDocument(
    to: '+1234567890',
    documentUrlOrId: 'https://example.com/invoice.pdf',
    filename: 'Invoice-July.pdf',  // optional, only applied for URL-based documents
    caption: 'Your July invoice'   // optional
);
```

#### Video

```php
CloudApiWhatsapp::sendVideo('+1234567890', 'https://example.com/intro.mp4', 'Intro video');
```

#### Audio

```php
CloudApiWhatsapp::sendAudio('+1234567890', 'https://example.com/voice.ogg');
```

#### Stickers

Stickers must be in `.webp` format.

```php
CloudApiWhatsapp::sendSticker('+1234567890', 'https://example.com/sticker.webp');
// or by Media ID
CloudApiWhatsapp::sendSticker('+1234567890', 'your-sticker-media-id');
```

#### Upload, retrieve, and delete media

```php
// Upload a local file and get back a Media ID
$response = CloudApiWhatsapp::uploadMedia(
    filePath: storage_path('app/invoice.pdf'),
    mimeType: 'application/pdf'
);
$mediaId = $response->json('id');

// Get metadata (includes temporary download URL)
$response = CloudApiWhatsapp::getMedia($mediaId);
$downloadUrl = $response->json('url');

// Delete
CloudApiWhatsapp::deleteMedia($mediaId);
```

---

### Location

```php
CloudApiWhatsapp::sendLocation(
    to: '+1234567890',
    latitude: 37.7749,
    longitude: -122.4194,
    name: 'Salesforce Tower',    // optional
    address: 'San Francisco, CA' // optional
);
```

### Contacts

```php
CloudApiWhatsapp::sendContact('+1234567890', [
    [
        'name' => [
            'first_name'     => 'Jane',
            'last_name'      => 'Doe',
            'formatted_name' => 'Jane Doe',
        ],
        'phones' => [
            ['phone' => '+1987654321', 'type' => 'MOBILE'],
        ],
        'emails' => [
            ['email' => 'jane@example.com', 'type' => 'WORK'],
        ],
    ],
]);
```

### Mark as Read

```php
CloudApiWhatsapp::markAsRead('wamid.HBgLMTIzNDU2Nzg5MA==');
```

### Raw Payload

For advanced use cases not covered by a dedicated method:

```php
CloudApiWhatsapp::sendRaw([
    'messaging_product' => 'whatsapp',
    'to' => '1234567890',
    'type' => 'text',
    'text' => ['body' => 'Custom payload'],
]);
```

---

### Business Profile

```php
// Read profile (returns about, address, description, email, websites, vertical, profile_picture_url)
$response = CloudApiWhatsapp::getBusinessProfile();

// Read specific fields only
$response = CloudApiWhatsapp::getBusinessProfile(['about', 'email']);

// Update profile
CloudApiWhatsapp::updateBusinessProfile([
    'about'    => 'We ship in 24 hours.',
    'email'    => 'support@yourcompany.com',
    'websites' => ['https://yourcompany.com'],
    'vertical' => 'RETAIL',
]);
```

---

### Template Management

Requires `WHATSAPP_BUSINESS_ACCOUNT_ID` to be set.

```php
// List all approved templates
$response = CloudApiWhatsapp::getTemplates(['status' => 'APPROVED']);

// List by name
$response = CloudApiWhatsapp::getTemplates(['name' => 'order_confirmation']);

// Create a new template
CloudApiWhatsapp::createTemplate([
    'name'       => 'order_shipped',
    'language'   => 'en_US',
    'category'   => 'UTILITY',
    'components' => [
        ['type' => 'BODY', 'text' => 'Your order {{1}} has shipped and will arrive by {{2}}.'],
    ],
]);

// Delete a template by name
CloudApiWhatsapp::deleteTemplate('old_promo_template');
```

---

### Webhooks

#### 1. Verify the webhook subscription (GET endpoint)

Meta sends a GET request to your webhook URL to verify ownership. Return the challenge value as a plain text response.

```php
// routes/web.php or routes/api.php
Route::get('/webhook/whatsapp', function (Request $request) {
    try {
        $challenge = CloudApiWhatsapp::verifyWebhook(
            queryParams: $request->query(),
            verifyToken: config('services.whatsapp.verify_token')
        );
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    } catch (\InvalidArgumentException $e) {
        abort(403, $e->getMessage());
    }
});
```

#### 2. Process incoming events (POST endpoint)

Meta sends a POST request with an HMAC-SHA256 signature in the `X-Hub-Signature-256` header. Always verify it before processing.

```php
Route::post('/webhook/whatsapp', function (Request $request) {
    try {
        $entries = CloudApiWhatsapp::parseWebhook(
            rawBody:   $request->getContent(),
            signature: $request->header('X-Hub-Signature-256'),
            appSecret: config('services.whatsapp.app_secret')
        );
    } catch (\InvalidArgumentException $e) {
        abort(403, $e->getMessage());
    }

    foreach ($entries as $entry) {
        foreach ($entry['changes'] as $change) {
            $messages = $change['value']['messages'] ?? [];
            foreach ($messages as $message) {
                // Handle $message['type'], $message['text']['body'], etc.
            }
        }
    }

    return response('EVENT_RECEIVED', 200);
});
```

---

## Error Handling

Every API method returns an `Illuminate\Http\Client\Response` object. The SDK does **not** throw exceptions on 4xx/5xx responses from Meta — you decide how to handle them.

### Checking the response

```php
$response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello!');

if ($response->failed()) {
    $error = $response->json('error');
    // $error['code']    — numeric Meta error code
    // $error['message'] — human-readable description
    // $error['type']    — e.g. OAuthException, GraphMethodException
}
```

### Common Meta error codes

| Code   | Meaning                                      | Action                                   |
|--------|----------------------------------------------|------------------------------------------|
| 0      | Unknown / generic error                      | Check message for details                |
| 10     | App does not have permission                 | Review app permissions in Meta dashboard |
| 100    | Invalid parameter                            | Check the request payload                |
| 130429 | Rate limit hit                               | Back off and retry after a delay         |
| 131030 | Recipient phone number not on WhatsApp       | Verify the number before sending         |
| 131047 | Re-engagement message not allowed            | Send a template to re-open the window    |
| 131051 | Message type unsupported for this recipient  | Try a different message type             |
| 190    | Access token expired or invalid              | Refresh or regenerate the token          |

### Throwing on failure

If you prefer exceptions over manual checks, chain `->throw()`:

```php
// Throws Illuminate\Http\Client\RequestException on any 4xx/5xx
$response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello!')->throw();

// Or handle specific status codes
$response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello!')
    ->throwIf(fn($r) => $r->status() === 401, new \RuntimeException('Token expired'));
```

### Network errors

Timeouts and connection failures throw `Illuminate\Http\Client\ConnectionException` regardless of the `->throw()` chain. Catch it at the boundary where you call the SDK:

```php
use Illuminate\Http\Client\ConnectionException;

try {
    $response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello!');
} catch (ConnectionException $e) {
    // Log and retry, or alert your team
}
```

### SDK-level exceptions

`InvalidArgumentException` is thrown before any HTTP request is made in these cases:

- Missing or empty `WHATSAPP_TOKEN` or `WHATSAPP_PHONE_NUMBER_ID`
- Missing `WHATSAPP_BUSINESS_ACCOUNT_ID` when calling template management methods
- Phone number contains fewer than 7 digits after stripping non-numeric characters
- File path passed to `uploadMedia()` does not exist
- Webhook verification token mismatch or invalid `hub.mode`
- Webhook payload signature does not match the expected HMAC-SHA256 hash

These are programmer-configuration errors. They should surface during development, not be silently swallowed in production.

---

## Dynamic Configuration (Multi-tenant)

Override the default token or phone number ID per request. The facade returns a cloned instance — the singleton is never mutated.

```php
$client = CloudApiWhatsapp::withToken('tenant-access-token')
    ->withPhoneNumberId('tenant-phone-number-id');

$client->sendMessage('+1234567890', 'Message from tenant account.');
```

---

## Testing

Since the package uses Laravel's native HTTP client, testing requires no real API connections:

```php
use Illuminate\Support\Facades\Http;
use Telmo\CloudApiWhatsapp\Facades\CloudApiWhatsapp;

public function test_sends_message(): void
{
    Http::fake([
        'graph.facebook.com/*' => Http::response([
            'messages' => [['id' => 'wamid.mock123']],
        ], 200),
    ]);

    $response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello!');

    $this->assertTrue($response->successful());

    Http::assertSent(function ($request) {
        return $request['to'] === '1234567890'
            && $request['text']['body'] === 'Hello!';
    });
}
```

Run the package's own test suite:

```bash
composer test
```

---

## License

The MIT License (MIT). Please see the [License File](LICENSE) for more information.
