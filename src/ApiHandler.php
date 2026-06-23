<?php

declare(strict_types=1);

namespace Xrea\Agent;

use Xrea\Agent\Capability\CapabilityRouter;

class ApiHandler
{
    private const VALID_ACTIONS = ['exec', 'ping', 'info', 'task.execute', 'task.status'];

    public function __construct(
        private readonly CommandRunner $runner,
        private readonly ?CapabilityRouter $capabilityRouter = null,
        private readonly bool $legacyExecEnabled = false,
        private readonly ?\Xrea\Agent\Task\TaskStoreInterface $taskStore = null,
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
            'task.execute' => $this->handleTaskExecute($body),
            'task.status' => $this->handleTaskStatus($body),
        };
    }

    private function handleExec(array $body): TaskResult
    {
        if (!$this->legacyExecEnabled) {
            return TaskResult::error('Action disabled: exec (use task.execute with process.runSafe instead)');
        }

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
            'max_execution_time' => (int) ini_get('max_execution_time'),
            'memory_limit' => ini_get('memory_limit'),
        ];

        return TaskResult::success($info);
    }

    private function handleTaskExecute(array $body): TaskResult
    {
        if (!isset($body['capability']) || !is_string($body['capability']) || $body['capability'] === '') {
            return TaskResult::error('Missing required field: capability');
        }

        // input must be an array; default to [] when missing
        $input = $body['input'] ?? [];
        if (!is_array($input)) {
            return TaskResult::error('Invalid field: input must be an object');
        }

        if ($this->capabilityRouter === null) {
            return TaskResult::error('Capability router is not configured');
        }

        // Check if recording requested — generate ID upfront so it's returned even on failure
        $record = !empty($body['record']);

        if ($record && $this->taskStore !== null) {
            // Generate a secure hex ID (32 chars, lowercase hex)
            $taskId = bin2hex(random_bytes(16));

            // Create pending record so the ID is always returned.
            // Sanitize capability name for command field (dots not allowed by regex).
            $safeCommand = str_replace('.', '-', $body['capability']);
            try {
                $this->taskStore->create($safeCommand, $taskId);
            } catch (\Throwable $e) {
                return TaskResult::error('Failed to create task record');
            }
        } else {
            $record = false; // explicitly disable for non-store cases
        }

        $result = $this->capabilityRouter->execute($body['capability'], $input);

        if ($record && $this->taskStore !== null) {
            try {
                // Serialize output to string for task store (handle arrays/objects)
                $output = match (true) {
                    is_string($result->data ?? null) => $result->data,
                    isset($result->data) && !is_array($result->data) => json_encode($result->data),
                    is_array($result->data) => json_encode($result->data),
                    default => '',
                };

                $this->taskStore->recordResult(
                    id: $taskId,
                    exitCode: (int) $result->exitCode,
                    output: $output,
                    duration: 0.0, // synchronous execution — no meaningful duration here
                    error: is_string($result->error) && $result->error !== '' ? $result->error : null,
                );

                return TaskResult::success([
                    'task_id' => $taskId,
                    'result'  => $result->toArray(),
                ]);
            } catch (\Throwable $e) {
                // Record failure but still return the result
                return TaskResult::error('Task recorded with error: ' . $e->getMessage());
            }
        }

        return $result;
    }

    private function handleTaskStatus(array $body): TaskResult
    {
        if ($this->taskStore === null) {
            return TaskResult::error('Task store is not configured');
        }

        $subAction = $body['status_action'] ?? '';

        // Validate sub-action early (before delegation)
        if ($subAction !== '' && !in_array($subAction, ['get', 'list'], true)) {
            return TaskResult::error("Invalid task.status action. Must be one of: get, list");
        }

        // When status_action is explicitly provided, delegate to sub-action handler
        if ($subAction !== '') {
            return $this->handleStatusSubAction($body, $subAction);
        }

        // Bare task_id lookup (spec-compliant): error when not found
        $taskId = $body['task_id'] ?? '';
        if (is_string($taskId) && $taskId !== '') {
            $record = $this->taskStore->get($taskId);
            if ($record === null) {
                return TaskResult::error('Task not found');
            }

            // Sanitize output for API response (limit size to prevent memory issues)
            $data = $record->toArray();
            if (isset($data['output']) && strlen((string) $data['output']) > 65536) {
                $data['output'] = substr((string) $data['output'], 0, 65536) . '... [truncated]';
            }

            return TaskResult::success(['task' => $data]);
        }

        return TaskResult::error("Invalid task.status action. Must be one of: get, list");
    }

    /**
     * Handle sub-action (get/list) with proper wrapping.
     */
    private function handleStatusSubAction(array $body, string $subAction): TaskResult
    {
        try {
            switch ($subAction) {
                case 'get':
                    $taskId = $body['task_id'] ?? '';
                    if ($taskId === '') {
                        return TaskResult::error('Missing required field: task_id');
                    }
                    $record = $this->taskStore->get($taskId);
                    if ($record === null) {
                        return TaskResult::success(['task' => null]);
                    }

                    // Sanitize output for API response (limit size to prevent memory issues)
                    $data = $record->toArray();
                    if (isset($data['output']) && strlen((string) $data['output']) > 65536) {
                        $data['output'] = substr((string) $data['output'], 0, 65536) . '... [truncated]';
                    }

                    return TaskResult::success(['task' => $data]);

                case 'list':
                    // Validate optional limit parameter (max 100)
                    $limit = isset($body['limit']) ? (int) $body['limit'] : null;
                    if ($limit !== null && $limit <= 0) {
                        return TaskResult::error('Limit must be a positive integer');
                    }

                    $records = $this->taskStore->listAll();
                    if ($limit !== null && $limit > 0) {
                        $records = array_slice($records, 0, min($limit, 100));
                    } else {
                        $records = array_slice($records, 0, 100);
                    }

                    // Sanitize outputs (truncate large outputs)
                    $sanitized = [];
                    foreach ($records as $recordData) {
                        if (isset($recordData['output']) && strlen((string) $recordData['output']) > 65536) {
                            $recordData['output'] = substr((string) $recordData['output'], 0, 65536) . '... [truncated]';
                        }
                        $sanitized[] = $recordData;
                    }

                    return TaskResult::success(['tasks' => $sanitized]);

                default:
                    return TaskResult::error("Unknown action: {$subAction}");
            }
        } catch (\Throwable $e) {
            return TaskResult::error('Task store error: ' . $e->getMessage());
        }
    }
}
