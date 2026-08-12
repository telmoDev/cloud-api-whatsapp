# Agent Instructions — telmodev/cloud-api-whatsapp

> This file is for AI coding assistants. The `telmodev/cloud-api-whatsapp` package is already installed in this project. Use this file as the single source of truth for how to implement it correctly.

---

## Package identity

| Key | Value |
|-----|-------|
| Packagist name | `telmodev/cloud-api-whatsapp` |
| Namespace | `Telmo\CloudApiWhatsapp` |
| Facade alias | `CloudApiWhatsapp` |
| Config key | `cloud-api-whatsapp` |

---

## How to import

```php
use Telmo\CloudApiWhatsapp\Facades\CloudApiWhatsapp;
```

All public methods are available statically through the facade. You can also resolve the class via dependency injection — see the "Dependency injection" section.

---

## Required environment variables

These must be present in `.env` for the SDK to work:

```env
WHATSAPP_TOKEN="..."
WHATSAPP_PHONE_NUMBER_ID="..."
```

`WHATSAPP_BUSINESS_ACCOUNT_ID` is only required for template management methods (`getTemplates`, `createTemplate`, `deleteTemplate`).

---

## Return type contract

Every API method returns `Illuminate\Http\Client\Response`. The SDK **never throws on 4xx/5xx API responses** — you must check the response.

```php
$response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello');

if ($response->failed()) {
    $error = $response->json('error');
    // $error['code']    — numeric Meta error code
    // $error['message'] — human-readable description
    // $error['type']    — e.g. OAuthException
}

// Prefer exceptions? Chain ->throw():
$response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hello')->throw();
```

---

## Exceptions thrown by the SDK

`InvalidArgumentException` is thrown **before** any HTTP request:

| Trigger | Message contains |
|---------|-----------------|
| Missing `WHATSAPP_TOKEN` | "token is not configured" |
| Missing `WHATSAPP_PHONE_NUMBER_ID` | "Phone Number ID is not configured" |
| Missing `WHATSAPP_BUSINESS_ACCOUNT_ID` on template calls | "Business Account ID is not configured" |
| Phone number has fewer than 7 digits | "Invalid phone number provided" |
| `uploadMedia()` path does not exist | "File not found at:" |
| Webhook `hub.mode` ≠ `subscribe` | "invalid hub.mode" |
| Webhook verify token mismatch | "token mismatch" |
| Webhook HMAC signature mismatch | "signature verification failed" |
| Webhook object type ≠ `whatsapp_business_account` | "unexpected object type" |

Network failures throw `Illuminate\Http\Client\ConnectionException`.

---

## Phone number formatting

The SDK strips all non-numeric characters automatically. Pass any common format:

```php
'+1 (234) 567-890'  →  '1234567890'  ✓
'+52-55-1234-5678'  →  '525512345678'  ✓
'invalid'           →  throws InvalidArgumentException
```

---

## Method reference

### Text messaging

```php
// Simple text
CloudApiWhatsapp::sendMessage(string $to, string $body, array $options = []): Response
// $options example: ['preview_url' => true]

// Reply quoting a previous message
CloudApiWhatsapp::replyToMessage(string $to, string $body, string $replyMessageId, array $options = []): Response

// Emoji reaction — pass empty string to remove an existing reaction
CloudApiWhatsapp::sendReaction(string $messageId, string $emoji): Response

// Mark a received message as read
CloudApiWhatsapp::markAsRead(string $messageId): Response

// Send a fully custom payload to the messages endpoint
CloudApiWhatsapp::sendRaw(array $payload): Response
```

### Template messages

```php
CloudApiWhatsapp::sendTemplate(
    string $to,
    string $templateName,
    string $languageCode,
    array $components = []
): Response
```

`$components` example:

```php
[
    [
        'type' => 'body',
        'parameters' => [
            ['type' => 'text', 'text' => 'John'],
        ],
    ],
]
```

### Interactive messages

```php
// Reply buttons (max 3)
CloudApiWhatsapp::sendButtons(
    string $to,
    string $body,
    array $buttons,        // [['id' => 'btn_id', 'title' => 'Label'], ...]
    ?string $header = null,
    ?string $footer = null
): Response

// List menu
CloudApiWhatsapp::sendList(
    string $to,
    string $body,
    string $buttonLabel,
    array $sections,       // [['title' => 'Section', 'rows' => [['id' => 'id', 'title' => 'Row', 'description' => '...'], ...]], ...]
    ?string $header = null,
    ?string $footer = null
): Response
```

### Media

URL vs Media ID detection is automatic — the SDK calls `filter_var($value, FILTER_VALIDATE_URL)` and sets `link` or `id` accordingly.

```php
CloudApiWhatsapp::sendImage(string $to, string $imageUrlOrId, ?string $caption = null): Response

CloudApiWhatsapp::sendDocument(
    string $to,
    string $documentUrlOrId,
    ?string $filename = null,  // only applied when $documentUrlOrId is a URL
    ?string $caption = null
): Response

CloudApiWhatsapp::sendVideo(string $to, string $videoUrlOrId, ?string $caption = null): Response

CloudApiWhatsapp::sendAudio(string $to, string $audioUrlOrId): Response

CloudApiWhatsapp::sendSticker(string $to, string $stickerUrlOrId): Response  // must be .webp

// Upload a local file — returns Media ID in ->json('id')
CloudApiWhatsapp::uploadMedia(string $filePath, string $mimeType): Response

// Get media metadata — temporary download URL in ->json('url'), valid ~5 min
CloudApiWhatsapp::getMedia(string $mediaId): Response

CloudApiWhatsapp::deleteMedia(string $mediaId): Response
```

### Location

```php
CloudApiWhatsapp::sendLocation(
    string $to,
    float $latitude,
    float $longitude,
    ?string $name = null,
    ?string $address = null
): Response
```

### Contacts

```php
CloudApiWhatsapp::sendContact(string $to, array $contacts): Response
```

`$contacts` structure:

```php
[
    [
        'name'   => ['first_name' => 'Jane', 'last_name' => 'Doe', 'formatted_name' => 'Jane Doe'],
        'phones' => [['phone' => '+1987654321', 'type' => 'MOBILE']],
        'emails' => [['email' => 'jane@example.com', 'type' => 'WORK']],
    ],
]
```

### Business Profile

```php
// Readable fields: about, address, description, email, websites, vertical, profile_picture_url
CloudApiWhatsapp::getBusinessProfile(array $fields = []): Response

// Writable fields: about, address, description, email, websites (array), vertical
CloudApiWhatsapp::updateBusinessProfile(array $data): Response
```

### Template management

> Requires `WHATSAPP_BUSINESS_ACCOUNT_ID`.

```php
CloudApiWhatsapp::getTemplates(array $filters = []): Response
// Filters: ['name' => 'my_template', 'status' => 'APPROVED']

CloudApiWhatsapp::createTemplate(array $template): Response
// Required keys: name, language, category, components

CloudApiWhatsapp::deleteTemplate(string $templateName): Response
```

### Webhooks

```php
// GET endpoint — verify Meta's subscription challenge
// Returns hub.challenge on success, throws InvalidArgumentException on failure
CloudApiWhatsapp::verifyWebhook(array $queryParams, string $verifyToken): string

// POST endpoint — validate signature and parse payload
// Returns the 'entry' array, throws InvalidArgumentException on invalid signature or wrong object type
CloudApiWhatsapp::parseWebhook(string $rawBody, string $signature, string $appSecret): array
```

Webhook routes:

```php
use Illuminate\Http\Request;
use Telmo\CloudApiWhatsapp\Facades\CloudApiWhatsapp;

Route::get('/webhook/whatsapp', function (Request $request) {
    try {
        $challenge = CloudApiWhatsapp::verifyWebhook(
            $request->query(),
            config('services.whatsapp.verify_token')
        );
        return response($challenge, 200)->header('Content-Type', 'text/plain');
    } catch (\InvalidArgumentException $e) {
        abort(403, $e->getMessage());
    }
});

Route::post('/webhook/whatsapp', function (Request $request) {
    try {
        $entries = CloudApiWhatsapp::parseWebhook(
            $request->getContent(),
            $request->header('X-Hub-Signature-256'),
            config('services.whatsapp.app_secret')
        );
    } catch (\InvalidArgumentException $e) {
        abort(403, $e->getMessage());
    }

    foreach ($entries as $entry) {
        foreach ($entry['changes'] as $change) {
            foreach ($change['value']['messages'] ?? [] as $message) {
                // $message['type'], $message['text']['body'], $message['id'], etc.
            }
        }
    }

    return response('EVENT_RECEIVED', 200);
});
```

---

## Dynamic configuration (multi-tenant)

`withToken()` and `withPhoneNumberId()` return a cloned instance — the singleton is never mutated.

```php
$client = CloudApiWhatsapp::withToken('tenant-token')
    ->withPhoneNumberId('tenant-phone-id');

$client->sendMessage('+1234567890', 'Hello from tenant!');
```

---

## Dependency injection

```php
use Telmo\CloudApiWhatsapp\CloudApiWhatsapp;

class NotificationService
{
    public function __construct(
        private CloudApiWhatsapp $whatsapp
    ) {}

    public function send(string $phone, string $message): void
    {
        $this->whatsapp->sendMessage($phone, $message);
    }
}
```

---

## Testing

The SDK uses Laravel's HTTP client — use `Http::fake()`, no real requests are made.

```php
use Illuminate\Support\Facades\Http;
use Telmo\CloudApiWhatsapp\Facades\CloudApiWhatsapp;

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
```

---

## Common Meta error codes

| Code | Meaning | Action |
|------|---------|--------|
| 10 | Permission denied | Review app permissions in Meta dashboard |
| 100 | Invalid parameter | Check request payload |
| 130429 | Rate limit hit | Back off and retry |
| 131030 | Recipient not on WhatsApp | Verify number before sending |
| 131047 | Re-engagement blocked | Send a template to reopen the 24h window |
| 190 | Token expired or invalid | Refresh or regenerate the access token |

---

## What the SDK does NOT do

- Does not throw on Meta API 4xx/5xx — always returns the raw `Response`.
- Does not retry failed requests.
- Does not queue messages.
- Does not parse incoming message content beyond signature validation — iterate `$entries` yourself.
