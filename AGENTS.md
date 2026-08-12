# AGENTS.md — telmodev/cloud-api-whatsapp (source repo)

Source of the `telmodev/cloud-api-whatsapp` Laravel package (published on Packagist), not a Laravel app.

## Commands

- Tests: `vendor/bin/phpunit` — **do not** use `composer test` (no such script in `composer.json`, despite README claiming it).
- Single test: `vendor/bin/phpunit --filter test_name`
- No lint, static analysis, or code-style tooling is configured (no Pint, phpstan, psalm, phpcs).
- Local PHP is 8.5; the package targets PHP ^8.2 and Laravel 10–12. `composer.lock` is gitignored.

## Layout

- `src/CloudApiWhatsapp.php` — the entire SDK: one ~890-line class holding every public method and the internal HTTP helpers.
- `src/CloudApiWhatsappServiceProvider.php` — binds the `cloud-api-whatsapp` singleton from the config; publishes config and `SKILL.md`.
- `src/Facades/CloudApiWhatsapp.php` — facade (accessor `cloud-api-whatsapp`); every public method has an `@method` stub here.
- `config/cloud-api-whatsapp.php` — reads `WHATSAPP_*` env vars; defaults `api_version` `v20.0`, `api_url` `https://graph.facebook.com`.
- `tests/CloudApiWhatsappTest.php` — the only test file.

## Behavior that's easy to get wrong

- Every API call goes through Laravel's `Http` facade (Bearer token + timeout) and returns a raw `Illuminate\Http\Client\Response`. The SDK never throws on 4xx/5xx — tests must assert on the response or via `Http::assertSent`.
- Local validation throws `InvalidArgumentException` before any request: missing token / phone number ID / business account ID, phone <7 digits, nonexistent upload path, webhook signature/token failures. Network failures surface as `ConnectionException`.
- `withToken()` / `withPhoneNumberId()` return a clone; the singleton is never mutated.
- `formatPhoneNumber()` strips all non-digit chars.
- `buildMediaPayload()` auto-detects URL vs Media ID with `filter_var(..., FILTER_VALIDATE_URL)` — no explicit param.
- `verifyWebhook()` reads both `hub_mode` and `hub.mode` style query keys.

## Testing quirks

- Tests extend `Orchestra\Testbench\TestCase`; config is set in `defineEnvironment()` — no `.env` needed.
- HTTP is faked with `Http::fake(['graph.facebook.com/*' => ...])` and asserted via `Http::assertSent`.
- The singleton caches config: tests that change config call `$this->app->forgetInstance('cloud-api-whatsapp')` (see `test_missing_business_account_id_throws_exception`).
- `uploadMedia()` uses `->attach()` (multipart) and is not covered by tests.

## Docs are consumer-facing

- `README.md` = usage docs for consumers. `resources/skills/cloud-api-whatsapp/SKILL.md` = the Agent Skill (open `SKILL.md` standard) published into consuming apps via `vendor:publish` (wired in `src/CloudApiWhatsappServiceProvider.php` boot). The provider registers per-provider tags (`cloud-api-whatsapp-agents-claude|opencode|codex|chatgpt|cursor|gemini|antigravity`) plus a combined `cloud-api-whatsapp-agents` tag that targets `.agents/skills/`. All tags copy the same skill directory.
- When adding a public method, keep three things in sync: the facade `@method` stubs in `src/Facades/CloudApiWhatsapp.php`, `README.md`, and `resources/skills/cloud-api-whatsapp/SKILL.md`. README/SKILL drift from the source occasionally — verify claims against `src/CloudApiWhatsapp.php`.
