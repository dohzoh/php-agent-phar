<?php

namespace Xrea\Agent\Console;

use Xrea\Agent\Config\ConfigManager;
use Xrea\Agent\AI\Client;

class Repl
{
    private const PROMPT = 'php-agent> ';
    private const WELCOME = 'PHP Agent v1.0. Type /quit to exit.';
    private array $history = [];

    public function __construct(
        private readonly ConfigManager $config,
    ) {
    }

    public function run(): never
    {
        if (!function_exists('readline')) {
            $this->runSimple();
        }

        echo self::WELCOME . "\n\n";

        while (true) {
            $input = readline(self::PROMPT);
            if ($input === false) {
                echo "\n";
                break;
            }

            $input = trim($input);
            readline_add_history($input);

            if ($this->handleCommand($input)) {
                break;
            }
        }
        exit(0);
    }

    private function runSimple(): never
    {
        echo self::WELCOME . "\n\n";
        echo "(readline not available, using basic input)\n\n";

        while (true) {
            echo self::PROMPT;
            $input = trim(fgets(STDIN) ?: '');
            if ($this->handleCommand($input)) {
                break;
            }
        }
        exit(0);
    }

    private function handleCommand(string $input): bool
    {
        if ($input === '') {
            return false;
        }

        if ($input === '/quit' || $input === '/exit') {
            echo "Goodbye.\n";
            return true;
        }

        if (str_starts_with($input, '/')) {
            echo "Unknown command: $input\n";
            return false;
        }

        $this->processPrompt($input);
        return false;
    }

    public function processPrompt(string $prompt): void
    {
        $this->history[] = $prompt;

        $provider = $this->config->get('ai.default_provider', 'xrea');
        $providers = $this->config->get('ai.providers', []);
        $providerConfig = $providers[$provider] ?? null;

        if (!$providerConfig) {
            echo "Error: AI provider '$provider' is not configured.\n";
            echo "Run: php-agent config set ai.default_provider <name>\n";
            return;
        }

        if (empty($providerConfig['url'])) {
            echo "Error: AI provider '$provider' has no URL configured.\n";
            return;
        }

        try {
            $client = Client::fromConfig($providerConfig);
            $start = microtime(true);
            $response = $client->chatSimple($prompt);
            $duration = microtime(true) - $start;

            echo "\n";
            echo $response;
            echo "\n\n";
            echo sprintf("[took %.2fs | provider: %s]\n", $duration, $provider);
        } catch (\Throwable $e) {
            echo "Error: " . $e->getMessage() . "\n";
        }
    }

    public function processPromptNonInteractive(string $prompt): string
    {
        $this->history[] = $prompt;

        $provider = $this->config->get('ai.default_provider', 'xrea');
        $providers = $this->config->get('ai.providers', []);
        $providerConfig = $providers[$provider] ?? null;

        if (!$providerConfig) {
            return "Error: AI provider '$provider' is not configured.";
        }

        if (empty($providerConfig['url'])) {
            return "Error: AI provider '$provider' has no URL configured.";
        }

        try {
            $client = Client::fromConfig($providerConfig);
            return $client->chatSimple($prompt);
        } catch (\Throwable $e) {
            return "Error: " . $e->getMessage();
        }
    }
}
