<?php

namespace Telmo\CloudApiWhatsapp;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use InvalidArgumentException;

/**
 * Laravel client for the Meta WhatsApp Cloud API.
 *
 * ## Error handling
 *
 * This SDK intentionally returns the raw `Illuminate\Http\Client\Response` object
 * from every API call. It does NOT throw exceptions on 4xx/5xx responses from Meta,
 * giving you full control over how your application reacts to each error.
 *
 * ### Checking for errors
 *
 * ```php
 * $response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hi');
 *
 * if ($response->failed()) {
 *     $error = $response->json('error');
 *     // $error['code']    — Meta error code (e.g. 131030 = invalid recipient)
 *     // $error['message'] — human-readable description
 *     // $error['type']    — error category (e.g. OAuthException, GraphMethodException)
 * }
 * ```
 *
 * ### Throwing on failure
 *
 * If you prefer exceptions, chain `->throw()` on the response:
 *
 * ```php
 * $response = CloudApiWhatsapp::sendMessage('+1234567890', 'Hi')->throw();
 * ```
 *
 * This raises `Illuminate\Http\Client\RequestException` on any 4xx/5xx status.
 *
 * ### Exceptions thrown by the SDK itself
 *
 * The SDK throws `InvalidArgumentException` for local validation failures
 * (missing credentials, invalid phone number, file not found, webhook token mismatch).
 * These are programmer errors that should be caught during development, not at runtime.
 *
 * Network-level timeouts and connection failures surface as
 * `Illuminate\Http\Client\ConnectionException` from Laravel's HTTP client.
 */
class CloudApiWhatsapp
{
    /**
     * The package configuration.
     */
    protected array $config;

    /**
     * The access token to use.
     */
    protected ?string $token = null;

    /**
     * The phone number ID to use.
     */
    protected ?string $phoneNumberId = null;

    /**
     * Create a new CloudApiWhatsapp instance.
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->token = $config['token'] ?? null;
        $this->phoneNumberId = $config['phone_number_id'] ?? null;
    }

    /**
     * Set the token dynamically (returns a clone, does not mutate the singleton).
     */
    public function withToken(string $token): self
    {
        $clone = clone $this;
        $clone->token = $token;
        return $clone;
    }

    /**
     * Set the phone number ID dynamically (returns a clone, does not mutate the singleton).
     */
    public function withPhoneNumberId(string $phoneNumberId): self
    {
        $clone = clone $this;
        $clone->phoneNumberId = $phoneNumberId;
        return $clone;
    }

    // -------------------------------------------------------------------------
    // Messaging
    // -------------------------------------------------------------------------

    /**
     * Send a template message.
     *
     * @param string $to            Recipient phone number (with country code, e.g. +1234567890)
     * @param string $templateName  Template name configured in Meta Business Manager
     * @param string $languageCode  Language code (e.g. en_US, es_ES)
     * @param array  $components    Variable components/parameters for the template
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendTemplate(string $to, string $templateName, string $languageCode, array $components = []): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'template',
            'template'          => [
                'name'     => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send a text message.
     *
     * @param string $to      Recipient phone number
     * @param string $body    Message body text
     * @param array  $options Additional options (e.g. ['preview_url' => true])
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendMessage(string $to, string $body, array $options = []): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'text',
            'text'              => array_merge([
                'body'        => $body,
                'preview_url' => false,
            ], $options),
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Reply to a specific message, quoting it in the conversation.
     *
     * @param string $to              Recipient phone number
     * @param string $body            Reply text
     * @param string $replyMessageId  The wamid of the message being replied to
     * @param array  $options         Additional text options (e.g. ['preview_url' => true])
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function replyToMessage(string $to, string $body, string $replyMessageId, array $options = []): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'context'           => ['message_id' => $replyMessageId],
            'type'              => 'text',
            'text'              => array_merge([
                'body'        => $body,
                'preview_url' => false,
            ], $options),
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * React to a message with an emoji.
     *
     * @param string $messageId  The wamid of the message to react to
     * @param string $emoji      A single emoji character (e.g. "👍"). Pass an empty string to remove a reaction.
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendReaction(string $messageId, string $emoji): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->getPhoneNumberId(), // reactions target is the sender's phone number id scope
            'type'              => 'reaction',
            'reaction'          => [
                'message_id' => $messageId,
                'emoji'      => $emoji,
            ],
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send an interactive message with reply buttons (up to 3 buttons).
     *
     * @param string      $to      Recipient phone number
     * @param string      $body    Body text shown above the buttons
     * @param array       $buttons Array of button definitions, each with 'id' and 'title' keys
     * @param string|null $header  Optional header text
     * @param string|null $footer  Optional footer text
     *
     * Example $buttons:
     * [
     *   ['id' => 'btn_yes', 'title' => 'Yes'],
     *   ['id' => 'btn_no',  'title' => 'No'],
     * ]
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendButtons(string $to, string $body, array $buttons, ?string $header = null, ?string $footer = null): Response
    {
        $interactive = [
            'type' => 'button',
            'body' => ['text' => $body],
            'action' => [
                'buttons' => array_map(fn($btn) => [
                    'type'  => 'reply',
                    'reply' => [
                        'id'    => $btn['id'],
                        'title' => $btn['title'],
                    ],
                ], $buttons),
            ],
        ];

        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer !== null) {
            $interactive['footer'] = ['text' => $footer];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send an interactive list message.
     *
     * @param string      $to          Recipient phone number
     * @param string      $body        Body text shown above the list
     * @param string      $buttonLabel Label for the button that opens the list
     * @param array       $sections    Array of section definitions (each with 'title' and 'rows' keys)
     * @param string|null $header      Optional header text
     * @param string|null $footer      Optional footer text
     *
     * Example $sections:
     * [
     *   [
     *     'title' => 'Options',
     *     'rows'  => [
     *       ['id' => 'opt_1', 'title' => 'Option 1', 'description' => 'Optional description'],
     *     ],
     *   ],
     * ]
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendList(string $to, string $body, string $buttonLabel, array $sections, ?string $header = null, ?string $footer = null): Response
    {
        $interactive = [
            'type' => 'list',
            'body' => ['text' => $body],
            'action' => [
                'button'   => $buttonLabel,
                'sections' => $sections,
            ],
        ];

        if ($header !== null) {
            $interactive['header'] = ['type' => 'text', 'text' => $header];
        }

        if ($footer !== null) {
            $interactive['footer'] = ['text' => $footer];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'interactive',
            'interactive'       => $interactive,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send an image.
     *
     * @param string      $to             Recipient phone number
     * @param string      $imageUrlOrId   Public URL or Meta Media ID of the image
     * @param string|null $caption        Optional caption
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendImage(string $to, string $imageUrlOrId, ?string $caption = null): Response
    {
        return $this->sendMedia($to, 'image', $imageUrlOrId, $caption);
    }

    /**
     * Send a document.
     *
     * @param string      $to                  Recipient phone number
     * @param string      $documentUrlOrId      Public URL or Meta Media ID of the document
     * @param string|null $filename             Filename shown to recipient (only used for URL-based documents)
     * @param string|null $caption              Optional caption
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendDocument(string $to, string $documentUrlOrId, ?string $filename = null, ?string $caption = null): Response
    {
        $mediaData = $this->buildMediaPayload($documentUrlOrId);

        if ($filename && filter_var($documentUrlOrId, FILTER_VALIDATE_URL)) {
            $mediaData['filename'] = $filename;
        }

        if ($caption) {
            $mediaData['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'document',
            'document'          => $mediaData,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send an audio file.
     *
     * @param string $to            Recipient phone number
     * @param string $audioUrlOrId  Public URL or Meta Media ID of the audio
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendAudio(string $to, string $audioUrlOrId): Response
    {
        return $this->sendMedia($to, 'audio', $audioUrlOrId);
    }

    /**
     * Send a video.
     *
     * @param string      $to            Recipient phone number
     * @param string      $videoUrlOrId  Public URL or Meta Media ID of the video
     * @param string|null $caption       Optional caption
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendVideo(string $to, string $videoUrlOrId, ?string $caption = null): Response
    {
        return $this->sendMedia($to, 'video', $videoUrlOrId, $caption);
    }

    /**
     * Send a sticker.
     *
     * @param string $to              Recipient phone number
     * @param string $stickerUrlOrId  Public URL or Meta Media ID of the sticker (must be .webp)
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendSticker(string $to, string $stickerUrlOrId): Response
    {
        return $this->sendMedia($to, 'sticker', $stickerUrlOrId);
    }

    /**
     * Send a location pin.
     *
     * @param string      $to         Recipient phone number
     * @param float       $latitude   Latitude coordinate
     * @param float       $longitude  Longitude coordinate
     * @param string|null $name       Optional location name
     * @param string|null $address    Optional address text
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendLocation(string $to, float $latitude, float $longitude, ?string $name = null, ?string $address = null): Response
    {
        $location = [
            'latitude'  => $latitude,
            'longitude' => $longitude,
        ];

        if ($name !== null) {
            $location['name'] = $name;
        }

        if ($address !== null) {
            $location['address'] = $address;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'location',
            'location'          => $location,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send contact details (vCard-like structure).
     *
     * @param string $to        Recipient phone number
     * @param array  $contacts  Array of contact structures per Meta specifications
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If the phone number or credentials are invalid
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendContact(string $to, array $contacts): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => 'contacts',
            'contacts'          => $contacts,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Mark a received message as read.
     *
     * @param string $messageId  The wamid of the message to mark as read
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function markAsRead(string $messageId): Response
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'status'            => 'read',
            'message_id'        => $messageId,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Send a fully custom payload directly to the messages endpoint.
     *
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function sendRaw(array $payload): Response
    {
        return $this->sendRequest('messages', $payload);
    }

    // -------------------------------------------------------------------------
    // Media
    // -------------------------------------------------------------------------

    /**
     * Upload a local file to Meta media servers.
     *
     * @param string $filePath  Absolute path to the file
     * @param string $mimeType  MIME type of the file (e.g. image/jpeg, audio/ogg)
     * @return Response  On success, ->json('id') contains the Media ID. Check ->failed() for errors.
     * @throws InvalidArgumentException  If the file does not exist or credentials are missing
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function uploadMedia(string $filePath, string $mimeType): Response
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found at: {$filePath}");
        }

        $filename   = basename($filePath);
        $fileStream = fopen($filePath, 'r');

        $url = sprintf(
            '%s/%s/%s/media',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getPhoneNumberId()
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->attach('file', $fileStream, $filename, ['Content-Type' => $mimeType])
            ->post($url, ['messaging_product' => 'whatsapp']);
    }

    /**
     * Retrieve metadata for an uploaded media object.
     *
     * On success, ->json('url') contains a temporary download URL (valid ~5 minutes).
     *
     * @param string $mediaId  Meta Media ID
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function getMedia(string $mediaId): Response
    {
        $url = sprintf('%s/%s/%s', $this->getApiUrl(), $this->getApiVersion(), $mediaId);

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->get($url);
    }

    /**
     * Delete an uploaded media object.
     *
     * @param string $mediaId  Meta Media ID
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function deleteMedia(string $mediaId): Response
    {
        $url = sprintf('%s/%s/%s', $this->getApiUrl(), $this->getApiVersion(), $mediaId);

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->delete($url);
    }

    // -------------------------------------------------------------------------
    // Business Profile
    // -------------------------------------------------------------------------

    /**
     * Retrieve the WhatsApp Business Profile for the configured phone number.
     *
     * Common fields returned: about, address, description, email, websites, vertical, profile_picture_url.
     *
     * @param array $fields  Specific fields to retrieve. Defaults to all main profile fields.
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function getBusinessProfile(array $fields = []): Response
    {
        $defaultFields = ['about', 'address', 'description', 'email', 'websites', 'vertical', 'profile_picture_url'];
        $fieldList     = implode(',', !empty($fields) ? $fields : $defaultFields);

        $url = sprintf(
            '%s/%s/%s/whatsapp_business_profile',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getPhoneNumberId()
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->get($url, ['fields' => $fieldList]);
    }

    /**
     * Update the WhatsApp Business Profile.
     *
     * @param array $data  Profile fields to update (e.g. ['about' => 'New description', 'email' => 'support@company.com'])
     *
     * Supported fields: about, address, description, email, websites (array), vertical.
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function updateBusinessProfile(array $data): Response
    {
        $url = sprintf(
            '%s/%s/%s/whatsapp_business_profile',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getPhoneNumberId()
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->post($url, array_merge($data, ['messaging_product' => 'whatsapp']));
    }

    // -------------------------------------------------------------------------
    // Template Management
    // -------------------------------------------------------------------------

    /**
     * List all message templates for the WhatsApp Business Account.
     *
     * @param array $filters  Optional filters: ['name' => 'my_template', 'status' => 'APPROVED']
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If business_account_id or credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function getTemplates(array $filters = []): Response
    {
        $url = sprintf(
            '%s/%s/%s/message_templates',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getBusinessAccountId()
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->get($url, $filters);
    }

    /**
     * Create a new message template.
     *
     * @param array $template  Template definition per Meta specifications.
     *
     * Example:
     * [
     *   'name'     => 'order_confirmation',
     *   'language' => 'en_US',
     *   'category' => 'UTILITY',
     *   'components' => [
     *     ['type' => 'BODY', 'text' => 'Your order {{1}} has been confirmed.'],
     *   ],
     * ]
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If business_account_id or credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function createTemplate(array $template): Response
    {
        $url = sprintf(
            '%s/%s/%s/message_templates',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getBusinessAccountId()
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->post($url, $template);
    }

    /**
     * Delete a message template by name.
     *
     * @param string $templateName  The name of the template to delete
     * @return Response  Check ->successful() / ->failed() for API-level errors
     * @throws InvalidArgumentException  If business_account_id or credentials are not configured
     * @throws ConnectionException       On network timeout or connection failure
     */
    public function deleteTemplate(string $templateName): Response
    {
        $url = sprintf(
            '%s/%s/%s/message_templates',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getBusinessAccountId()
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->delete($url, ['name' => $templateName]);
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    /**
     * Verify a Meta webhook subscription challenge.
     *
     * Call this from your webhook GET endpoint. Returns the hub.challenge value
     * if verification succeeds, or throws InvalidArgumentException if it fails.
     *
     * @param array  $queryParams   The full query string parameters from the incoming GET request
     * @param string $verifyToken   Your configured webhook verify token
     *
     * @throws InvalidArgumentException  When the token does not match or the mode is invalid
     */
    public function verifyWebhook(array $queryParams, string $verifyToken): string
    {
        $mode      = $queryParams['hub_mode']          ?? $queryParams['hub.mode']          ?? null;
        $token     = $queryParams['hub_verify_token']  ?? $queryParams['hub.verify_token']  ?? null;
        $challenge = $queryParams['hub_challenge']     ?? $queryParams['hub.challenge']     ?? null;

        if ($mode !== 'subscribe') {
            throw new InvalidArgumentException("Webhook verification failed: invalid hub.mode '{$mode}'.");
        }

        if ($token !== $verifyToken) {
            throw new InvalidArgumentException("Webhook verification failed: token mismatch.");
        }

        return (string) $challenge;
    }

    /**
     * Parse and validate an incoming webhook payload from Meta.
     *
     * Returns a normalised array of webhook entry objects. Each entry contains
     * the 'id' of the WhatsApp Business Account and its 'changes' array.
     *
     * @param string $rawBody    The raw JSON request body
     * @param string $signature  The X-Hub-Signature-256 header value (e.g. "sha256=abc...")
     * @param string $appSecret  Your Meta App Secret, used to verify the signature
     *
     * @throws InvalidArgumentException  When the signature is invalid or the payload is malformed
     */
    public function parseWebhook(string $rawBody, string $signature, string $appSecret): array
    {
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $rawBody, $appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException("Webhook signature verification failed.");
        }

        $payload = json_decode($rawBody, true);

        if (!isset($payload['object']) || $payload['object'] !== 'whatsapp_business_account') {
            throw new InvalidArgumentException("Invalid webhook payload: unexpected object type.");
        }

        return $payload['entry'] ?? [];
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /**
     * Build and send a media message payload.
     */
    protected function sendMedia(string $to, string $type, string $urlOrId, ?string $caption = null): Response
    {
        $mediaData = $this->buildMediaPayload($urlOrId);

        if ($caption !== null) {
            $mediaData['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $this->formatPhoneNumber($to),
            'type'              => $type,
            $type               => $mediaData,
        ];

        return $this->sendRequest('messages', $payload);
    }

    /**
     * Build the media sub-object: link for URLs, id for Media IDs.
     */
    protected function buildMediaPayload(string $urlOrId): array
    {
        if (filter_var($urlOrId, FILTER_VALIDATE_URL)) {
            return ['link' => $urlOrId];
        }

        return ['id' => $urlOrId];
    }

    /**
     * Strip non-digit characters and validate the result is a plausible phone number.
     *
     * @throws InvalidArgumentException  When the result has fewer than 7 digits
     */
    protected function formatPhoneNumber(string $phoneNumber): string
    {
        $cleanNumber = preg_replace('/\D/', '', $phoneNumber);

        if (strlen($cleanNumber) < 7) {
            throw new InvalidArgumentException("Invalid phone number provided: {$phoneNumber}");
        }

        return $cleanNumber;
    }

    /**
     * Perform a POST request to the Meta Graph API messages endpoint.
     */
    protected function sendRequest(string $endpoint, array $payload): Response
    {
        $url = sprintf(
            '%s/%s/%s/%s',
            $this->getApiUrl(),
            $this->getApiVersion(),
            $this->getPhoneNumberId(),
            $endpoint
        );

        return Http::withToken($this->getToken())
            ->timeout($this->getTimeout())
            ->post($url, $payload);
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getToken(): string
    {
        if (!$this->token) {
            throw new InvalidArgumentException("WhatsApp Cloud API token is not configured.");
        }

        return $this->token;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getPhoneNumberId(): string
    {
        if (!$this->phoneNumberId) {
            throw new InvalidArgumentException("WhatsApp Phone Number ID is not configured.");
        }

        return $this->phoneNumberId;
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function getBusinessAccountId(): string
    {
        $id = $this->config['business_account_id'] ?? null;

        if (!$id) {
            throw new InvalidArgumentException("WhatsApp Business Account ID is not configured.");
        }

        return $id;
    }

    protected function getApiVersion(): string
    {
        return $this->config['api_version'] ?? 'v20.0';
    }

    protected function getApiUrl(): string
    {
        return $this->config['api_url'] ?? 'https://graph.facebook.com';
    }

    protected function getTimeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }
}
