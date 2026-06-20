<?php

namespace Xrea\Agent;

use Xrea\Agent\Config\ConfigManager;
use Xrea\Agent\Console\Repl;

class Agent
{
    private ConfigManager $config;

    public function __construct(?string $configDir = null)
    {
        $this->config = new ConfigManager($configDir);
    }

    public function runRepl(): never
    {
        $repl = new Repl($this->config);
        $repl->run();
    }

    public function runPrompt(string $prompt): string
    {
        $repl = new Repl($this->config);
        return $repl->processPromptNonInteractive($prompt);
    }

    public function runCommand(string $command): TaskResult
    {
        $runner = new CommandRunner();
        $handler = new ApiHandler($runner);
        return $handler->handle([
            'action' => 'exec',
            'command' => $command,
        ]);
    }

    public function getConfig(): ConfigManager
    {
        return $this->config;
    }

    public function initConfig(): void
    {
        $path = $this->config->getConfigPath();
        if (!file_exists($path)) {
            $this->config->save();
            echo "Created config: $path\n";
        } else {
            echo "Config already exists: $path\n";
        }
    }

    public function showConfig(): void
    {
        echo json_encode($this->config->getAll(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    }
}
