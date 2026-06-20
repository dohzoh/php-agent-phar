<?php

namespace Xrea\Agent;

class ApiHandler
{
    private const VALID_ACTIONS = ['exec', 'ping', 'info'];

    public function __construct(
        private readonly CommandRunner $runner,
    ) {
    }

    public function handle(array $body): TaskResult
    {
        $action = $body['action'] ?? '';

        if (!in_array($action, self::VALID_ACTIONS, true)) {
            return TaskResult::error("Invalid action. Must be one of: " . implode(', ', self::VALID_ACTIONS));
        }

        return match ($action) {
            'ping' => TaskResult::success('pong'),
            'info' => $this->handleInfo(),
            'exec' => $this->handleExec($body),
        };
    }

    private function handleExec(array $body): TaskResult
    {
        $command = $body['command'] ?? '';
        if ($command === '') {
            return TaskResult::error('Command is required');
        }

        if (!CommandRunner::isSafeCommand($command)) {
            return TaskResult::error("Command not in safe list: " . ($body['command'] ?? ''));
        }

        $timeout = isset($body['timeout']) ? (int) $body['timeout'] : null;

        return $this->runner->run($command, $timeout);
    }

    private function handleInfo(): TaskResult
    {
        $info = [
            'php_version' => PHP_VERSION,
            'os' => PHP_OS,
            'uname' => php_uname('a'),
            'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'cli',
            'extensions' => get_loaded_extensions(),
            'disabled_functions' => ini_get('disable_functions'),
            'max_execution_time' => ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
        ];

        return TaskResult::success($info);
    }
}
