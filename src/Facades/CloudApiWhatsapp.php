<?php

namespace Telmo\CloudApiWhatsapp\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Illuminate\Http\Client\Response sendTemplate(string $to, string $templateName, string $languageCode, array $components = [])
 * @method static \Illuminate\Http\Client\Response sendMessage(string $to, string $body, array $options = [])
 * @method static \Illuminate\Http\Client\Response replyToMessage(string $to, string $body, string $replyMessageId, array $options = [])
 * @method static \Illuminate\Http\Client\Response sendReaction(string $messageId, string $emoji)
 * @method static \Illuminate\Http\Client\Response sendButtons(string $to, string $body, array $buttons, string|null $header = null, string|null $footer = null)
 * @method static \Illuminate\Http\Client\Response sendList(string $to, string $body, string $buttonLabel, array $sections, string|null $header = null, string|null $footer = null)
 * @method static \Illuminate\Http\Client\Response sendImage(string $to, string $imageUrlOrId, string|null $caption = null)
 * @method static \Illuminate\Http\Client\Response sendDocument(string $to, string $documentUrlOrId, string|null $filename = null, string|null $caption = null)
 * @method static \Illuminate\Http\Client\Response sendAudio(string $to, string $audioUrlOrId)
 * @method static \Illuminate\Http\Client\Response sendVideo(string $to, string $videoUrlOrId, string|null $caption = null)
 * @method static \Illuminate\Http\Client\Response sendSticker(string $to, string $stickerUrlOrId)
 * @method static \Illuminate\Http\Client\Response sendLocation(string $to, float $latitude, float $longitude, string|null $name = null, string|null $address = null)
 * @method static \Illuminate\Http\Client\Response sendContact(string $to, array $contacts)
 * @method static \Illuminate\Http\Client\Response markAsRead(string $messageId)
 * @method static \Illuminate\Http\Client\Response sendRaw(array $payload)
 * @method static \Illuminate\Http\Client\Response uploadMedia(string $filePath, string $mimeType)
 * @method static \Illuminate\Http\Client\Response getMedia(string $mediaId)
 * @method static \Illuminate\Http\Client\Response deleteMedia(string $mediaId)
 * @method static \Illuminate\Http\Client\Response getBusinessProfile(array $fields = [])
 * @method static \Illuminate\Http\Client\Response updateBusinessProfile(array $data)
 * @method static \Illuminate\Http\Client\Response getTemplates(array $filters = [])
 * @method static \Illuminate\Http\Client\Response createTemplate(array $template)
 * @method static \Illuminate\Http\Client\Response deleteTemplate(string $templateName)
 * @method static string                            verifyWebhook(array $queryParams, string $verifyToken)
 * @method static array                             parseWebhook(string $rawBody, string $signature, string $appSecret)
 * @method static \Telmo\CloudApiWhatsapp\CloudApiWhatsapp withToken(string $token)
 * @method static \Telmo\CloudApiWhatsapp\CloudApiWhatsapp withPhoneNumberId(string $phoneNumberId)
 *
 * @see \Telmo\CloudApiWhatsapp\CloudApiWhatsapp
 */
class CloudApiWhatsapp extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'cloud-api-whatsapp';
    }
}
