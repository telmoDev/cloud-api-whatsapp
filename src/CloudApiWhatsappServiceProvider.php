<?php

namespace Telmo\CloudApiWhatsapp;

use Illuminate\Support\ServiceProvider;

class CloudApiWhatsappServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Merge config
        $this->mergeConfigFrom(
            __DIR__ . '/../config/cloud-api-whatsapp.php',
            'cloud-api-whatsapp'
        );

        // Bind main class
        $this->app->singleton('cloud-api-whatsapp', function ($app) {
            $config = $app['config']->get('cloud-api-whatsapp');
            return new CloudApiWhatsapp($config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            // Publish config
            $this->publishes([
                __DIR__ . '/../config/cloud-api-whatsapp.php' => config_path('cloud-api-whatsapp.php'),
            ], 'cloud-api-whatsapp-config');

            // Publish the Agent Skill (SKILL.md) to per-provider skill directories.
            // Every provider reads the open Agent Skills format (name + description
            // frontmatter); only the install directory differs per tool.
            $skillSource = __DIR__ . '/../resources/skills/cloud-api-whatsapp';

            $this->publishes([
                $skillSource => base_path('.claude/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-claude');

            $this->publishes([
                $skillSource => base_path('.opencode/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-opencode');

            $this->publishes([
                $skillSource => base_path('.agents/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-codex');

            $this->publishes([
                $skillSource => base_path('.agents/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-chatgpt');

            $this->publishes([
                $skillSource => base_path('.cursor/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-cursor');

            $this->publishes([
                $skillSource => base_path('.gemini/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-gemini');

            $this->publishes([
                $skillSource => base_path('.agents/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents-antigravity');

            // Combined: installs into the cross-tool .agents/skills location, which is
            // read by opencode, Codex, ChatGPT, Cursor, Gemini CLI, and Antigravity.
            // Claude Code does not read .agents/skills — use the -claude tag instead.
            $this->publishes([
                $skillSource => base_path('.agents/skills/cloud-api-whatsapp'),
            ], 'cloud-api-whatsapp-agents');
        }
    }
}
