<?php

namespace Telmo\CloudApiWhatsapp\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase;
use Telmo\CloudApiWhatsapp\CloudApiWhatsapp;
use Telmo\CloudApiWhatsapp\CloudApiWhatsappServiceProvider;
use Telmo\CloudApiWhatsapp\Facades\CloudApiWhatsapp as Facade;

class CloudApiWhatsappTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [CloudApiWhatsappServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cloud-api-whatsapp.token', 'test-token');
        $app['config']->set('cloud-api-whatsapp.phone_number_id', 'test-phone-id');
        $app['config']->set('cloud-api-whatsapp.business_account_id', 'test-biz-account-id');
        $app['config']->set('cloud-api-whatsapp.api_url', 'https://graph.facebook.com');
        $app['config']->set('cloud-api-whatsapp.api_version', 'v20.0');
    }

    // -------------------------------------------------------------------------
    // Existing tests (kept intact)
    // -------------------------------------------------------------------------

    public function test_facade_resolves_instance(): void
    {
        $this->assertInstanceOf(CloudApiWhatsapp::class, Facade::getFacadeRoot());
    }

    public function test_send_message_sends_correct_payload(): void
    {
        Http::fake([
            'graph.facebook.com/v20.0/test-phone-id/messages' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts'          => [['input' => '1234567890', 'wa_id' => '1234567890']],
                'messages'          => [['id' => 'wamid.HBgLMTIzNDU2Nzg5MA==']],
            ], 200),
        ]);

        $response = Facade::sendMessage('+1 (234) 567-890', 'Hello World!');

        $this->assertTrue($response->successful());
        $this->assertEquals('wamid.HBgLMTIzNDU2Nzg5MA==', $response->json('messages.0.id'));

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v20.0/test-phone-id/messages'
                && $request->method() === 'POST'
                && $request->hasHeader('Authorization', 'Bearer test-token')
                && $request['to'] === '1234567890'
                && $request['type'] === 'text'
                && $request['text']['body'] === 'Hello World!';
        });
    }

    public function test_send_template_sends_correct_payload(): void
    {
        Http::fake();

        Facade::sendTemplate('1234567890', 'welcome_template', 'en_US', [
            [
                'type'       => 'body',
                'parameters' => [['type' => 'text', 'text' => 'John Doe']],
            ],
        ]);

        Http::assertSent(function ($request) {
            return $request['type'] === 'template'
                && $request['template']['name'] === 'welcome_template'
                && $request['template']['language']['code'] === 'en_US'
                && $request['template']['components'][0]['type'] === 'body'
                && $request['template']['components'][0]['parameters'][0]['text'] === 'John Doe';
        });
    }

    public function test_dynamic_credentials(): void
    {
        Http::fake();

        $client = Facade::withToken('dynamic-token')->withPhoneNumberId('dynamic-phone-id');
        $client->sendMessage('1234567890', 'Hi');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://graph.facebook.com/v20.0/dynamic-phone-id/messages'
                && $request->hasHeader('Authorization', 'Bearer dynamic-token');
        });
    }

    public function test_invalid_phone_number_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Facade::sendMessage('invalid-number', 'Hi');
    }

    // -------------------------------------------------------------------------
    // Reply to message
    // -------------------------------------------------------------------------

    public function test_reply_to_message_includes_context(): void
    {
        Http::fake();

        Facade::replyToMessage('1234567890', 'Got it!', 'wamid.original123');

        Http::assertSent(function ($request) {
            return $request['type'] === 'text'
                && $request['text']['body'] === 'Got it!'
                && $request['context']['message_id'] === 'wamid.original123';
        });
    }

    // -------------------------------------------------------------------------
    // Reactions
    // -------------------------------------------------------------------------

    public function test_send_reaction_sends_correct_payload(): void
    {
        Http::fake();

        Facade::sendReaction('wamid.target456', '👍');

        Http::assertSent(function ($request) {
            return $request['type'] === 'reaction'
                && $request['reaction']['message_id'] === 'wamid.target456'
                && $request['reaction']['emoji'] === '👍';
        });
    }

    public function test_send_reaction_empty_emoji_removes_reaction(): void
    {
        Http::fake();

        Facade::sendReaction('wamid.target789', '');

        Http::assertSent(function ($request) {
            return $request['type'] === 'reaction'
                && $request['reaction']['emoji'] === '';
        });
    }

    // -------------------------------------------------------------------------
    // Interactive messages
    // -------------------------------------------------------------------------

    public function test_send_buttons_sends_correct_payload(): void
    {
        Http::fake();

        Facade::sendButtons('1234567890', 'Choose an option', [
            ['id' => 'btn_yes', 'title' => 'Yes'],
            ['id' => 'btn_no',  'title' => 'No'],
        ], 'Confirmation', 'Powered by Kiro');

        Http::assertSent(function ($request) {
            $interactive = $request['interactive'];

            return $request['type'] === 'interactive'
                && $interactive['type'] === 'button'
                && $interactive['body']['text'] === 'Choose an option'
                && $interactive['header']['text'] === 'Confirmation'
                && $interactive['footer']['text'] === 'Powered by Kiro'
                && $interactive['action']['buttons'][0]['reply']['id'] === 'btn_yes'
                && $interactive['action']['buttons'][1]['reply']['id'] === 'btn_no';
        });
    }

    public function test_send_buttons_without_optional_fields(): void
    {
        Http::fake();

        Facade::sendButtons('1234567890', 'Pick one', [
            ['id' => 'opt_a', 'title' => 'A'],
        ]);

        Http::assertSent(function ($request) {
            $interactive = $request['interactive'];

            return $request['type'] === 'interactive'
                && !isset($interactive['header'])
                && !isset($interactive['footer']);
        });
    }

    public function test_send_list_sends_correct_payload(): void
    {
        Http::fake();

        Facade::sendList('1234567890', 'Select a product', 'View options', [
            [
                'title' => 'Electronics',
                'rows'  => [
                    ['id' => 'prod_1', 'title' => 'Laptop',       'description' => '15" display'],
                    ['id' => 'prod_2', 'title' => 'Smartphone',   'description' => '6.5" OLED'],
                ],
            ],
        ]);

        Http::assertSent(function ($request) {
            $interactive = $request['interactive'];

            return $request['type'] === 'interactive'
                && $interactive['type'] === 'list'
                && $interactive['action']['button'] === 'View options'
                && $interactive['action']['sections'][0]['title'] === 'Electronics'
                && $interactive['action']['sections'][0]['rows'][0]['id'] === 'prod_1';
        });
    }

    // -------------------------------------------------------------------------
    // Stickers
    // -------------------------------------------------------------------------

    public function test_send_sticker_by_url(): void
    {
        Http::fake();

        Facade::sendSticker('1234567890', 'https://example.com/sticker.webp');

        Http::assertSent(function ($request) {
            return $request['type'] === 'sticker'
                && $request['sticker']['link'] === 'https://example.com/sticker.webp';
        });
    }

    public function test_send_sticker_by_media_id(): void
    {
        Http::fake();

        Facade::sendSticker('1234567890', 'media-id-abc123');

        Http::assertSent(function ($request) {
            return $request['type'] === 'sticker'
                && $request['sticker']['id'] === 'media-id-abc123';
        });
    }

    // -------------------------------------------------------------------------
    // Business Profile
    // -------------------------------------------------------------------------

    public function test_get_business_profile_calls_correct_endpoint(): void
    {
        Http::fake([
            'graph.facebook.com/v20.0/test-phone-id/whatsapp_business_profile*' => Http::response([
                'data' => [['about' => 'We help you ship faster.']],
            ], 200),
        ]);

        $response = Facade::getBusinessProfile();

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'whatsapp_business_profile')
                && $request->method() === 'GET'
                && str_contains($request->url(), 'fields=');
        });
    }

    public function test_update_business_profile_sends_correct_payload(): void
    {
        Http::fake();

        Facade::updateBusinessProfile([
            'about' => 'New tagline',
            'email' => 'support@example.com',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'whatsapp_business_profile')
                && $request->method() === 'POST'
                && $request['about'] === 'New tagline'
                && $request['email'] === 'support@example.com'
                && $request['messaging_product'] === 'whatsapp';
        });
    }

    // -------------------------------------------------------------------------
    // Template Management
    // -------------------------------------------------------------------------

    public function test_get_templates_calls_correct_endpoint(): void
    {
        Http::fake([
            'graph.facebook.com/v20.0/test-biz-account-id/message_templates*' => Http::response([
                'data' => [['name' => 'welcome', 'status' => 'APPROVED']],
            ], 200),
        ]);

        $response = Facade::getTemplates(['status' => 'APPROVED']);

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'message_templates')
                && $request->method() === 'GET';
        });
    }

    public function test_create_template_sends_correct_payload(): void
    {
        Http::fake();

        Facade::createTemplate([
            'name'       => 'order_shipped',
            'language'   => 'en_US',
            'category'   => 'UTILITY',
            'components' => [
                ['type' => 'BODY', 'text' => 'Your order {{1}} has shipped.'],
            ],
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'message_templates')
                && $request->method() === 'POST'
                && $request['name'] === 'order_shipped'
                && $request['category'] === 'UTILITY';
        });
    }

    public function test_delete_template_sends_correct_payload(): void
    {
        Http::fake();

        Facade::deleteTemplate('old_template');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'message_templates')
                && $request->method() === 'DELETE'
                && $request['name'] === 'old_template';
        });
    }

    public function test_missing_business_account_id_throws_exception(): void
    {
        $this->app['config']->set('cloud-api-whatsapp.business_account_id', null);
        // Force the singleton to re-resolve with the updated config
        $this->app->forgetInstance('cloud-api-whatsapp');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Business Account ID');

        Facade::clearResolvedInstance('cloud-api-whatsapp');
        $this->app->make('cloud-api-whatsapp')->getTemplates();
    }

    // -------------------------------------------------------------------------
    // Webhook
    // -------------------------------------------------------------------------

    public function test_verify_webhook_returns_challenge_on_success(): void
    {
        $challenge = Facade::verifyWebhook([
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'my-secret-token',
            'hub.challenge'    => 'abc123xyz',
        ], 'my-secret-token');

        $this->assertEquals('abc123xyz', $challenge);
    }

    public function test_verify_webhook_throws_on_wrong_mode(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('hub.mode');

        Facade::verifyWebhook([
            'hub.mode'         => 'unsubscribe',
            'hub.verify_token' => 'my-secret-token',
            'hub.challenge'    => 'abc123xyz',
        ], 'my-secret-token');
    }

    public function test_verify_webhook_throws_on_token_mismatch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('token mismatch');

        Facade::verifyWebhook([
            'hub.mode'         => 'subscribe',
            'hub.verify_token' => 'wrong-token',
            'hub.challenge'    => 'abc123xyz',
        ], 'correct-token');
    }

    public function test_parse_webhook_validates_signature_and_returns_entries(): void
    {
        $appSecret = 'my-app-secret';
        $payload   = json_encode([
            'object' => 'whatsapp_business_account',
            'entry'  => [
                [
                    'id'      => '123456789',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'messages'          => [
                                    ['id' => 'wamid.incoming001', 'type' => 'text', 'text' => ['body' => 'Hello!']],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        $entries = Facade::parseWebhook($payload, $signature, $appSecret);

        $this->assertCount(1, $entries);
        $this->assertEquals('123456789', $entries[0]['id']);
        $this->assertEquals('wamid.incoming001', $entries[0]['changes'][0]['value']['messages'][0]['id']);
    }

    public function test_parse_webhook_throws_on_invalid_signature(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('signature verification failed');

        Facade::parseWebhook('{"object":"whatsapp_business_account","entry":[]}', 'sha256=invalidsig', 'my-app-secret');
    }

    public function test_parse_webhook_throws_on_wrong_object_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('unexpected object type');

        $rawBody   = json_encode(['object' => 'page', 'entry' => []]);
        $signature = 'sha256=' . hash_hmac('sha256', $rawBody, 'secret');

        Facade::parseWebhook($rawBody, $signature, 'secret');
    }
}
