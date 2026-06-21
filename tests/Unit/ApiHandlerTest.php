<?php

declare(strict_types=1);

use Xrea\Agent\ApiHandler;
use Xrea\Agent\CommandRunner;
use Xrea\Agent\Capability\CapabilityRouter;
use Xrea\Agent\Capability\SystemInfoCapability;

it('responds to ping', function (): void {
    $handler = new ApiHandler(new CommandRunner());

    $result = $handler->handle(['action' => 'ping']);

    expect($result->status)->toBe('success')
        ->and($result->data)->toBe('pong')
        ->and($result->exitCode)->toBe(0);
});

it('rejects unknown actions', function (): void {
    $handler = new ApiHandler(new CommandRunner());

    $result = $handler->handle(['action' => 'unknown']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('Invalid action');
});

it('legacy exec is disabled by default', function (): void {
    $handler = new ApiHandler(new CommandRunner());

    $result = $handler->handle(['action' => 'exec']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('disabled');
});

it('legacy exec returns command required when enabled', function (): void {
    $runner = new CommandRunner();
    $handler = new ApiHandler($runner, null, legacyExecEnabled: true);

    $result = $handler->handle(['action' => 'exec']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toBe('Command is required');
});

it('task.execute requires capability', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);
    $handler = new ApiHandler(new CommandRunner(), $router);

    $result = $handler->handle(['action' => 'task.execute']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('Missing required field: capability');
});

it('task.execute rejects non-array input', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);
    $handler = new ApiHandler(new CommandRunner(), $router);

    $result = $handler->handle([
        'action' => 'task.execute',
        'capability' => 'system.info',
        'input' => 'not-an-array',
    ]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('input must be an object');
});

it('task.execute can call system.info', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);
    $handler = new ApiHandler(new CommandRunner(), $router);

    $result = $handler->handle([
        'action' => 'task.execute',
        'capability' => 'system.info',
        'input' => [],
    ]);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data))->toBeTrue()
        ->and($result->data['php_version'])->not->toBeNull();
});

it('existing info still works with router', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);
    $handler = new ApiHandler(new CommandRunner(), $router);

    $result = $handler->handle(['action' => 'info']);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data))->toBeTrue();
});

it('task.status.get returns task data when store configured', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $store->create('php -v', 'task-100');

    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'get',
        'task_id' => 'task-100',
    ]);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data))->toBeTrue()
        ->and($result->data['task']['id'])->toBe('task-100');

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status.get returns null task for non-existent ID', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'get',
        'task_id' => 'nonexistent-task',
    ]);

    expect($result->status)->toBe('success')
        ->and($result->data['task'])->toBeNull();

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status.list returns all tasks when store configured', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $store->create('php -v', 'task-101');
    $store->create('git status', 'task-102');

    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'list',
    ]);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data['tasks']))->toBeTrue()
        ->and(count($result->data['tasks']))->toBe(2);

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status.list respects limit parameter', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    for ($i = 1; $i <= 10; $i++) {
        $store->create("echo {$i}"); // auto-generated ID
    }

    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'list',
        'limit' => 3,
    ]);

    expect($result->status)->toBe('success')
        ->and(count($result->data['tasks']))->toBe(3);

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status rejects without store', function (): void {
    $handler = new ApiHandler(new CommandRunner());

    $result = $handler->handle(['action' => 'task.status', 'status_action' => 'get']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('not configured');
});

it('task.status rejects invalid action parameter', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'invalid-action',
    ]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('Must be one of: get, list');

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status.get rejects missing task_id', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'get',
    ]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('task_id');

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status.list limit rejects non-positive values', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'status_action' => 'list',
        'limit' => -1,
    ]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('positive integer');

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.execute with record:true returns task_id and result', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $router = new CapabilityRouter([new SystemInfoCapability()]);
    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $handler = new ApiHandler(new CommandRunner(), $router, legacyExecEnabled: false, taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.execute',
        'capability' => 'system.info',
        'input' => [],
        'record' => true,
    ]);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data))->toBeTrue()
        ->and(isset($result->data['task_id']))->toBeTrue();

    // Verify the task was actually recorded in the store
    $record = $store->get($result->data['task_id']);
    expect($record)->not->toBeNull()
        ->and($record->status())->toBe('completed')
        ->and(is_string($result->data['task_id']))->toBeTrue();

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});

it('task.status returns error for unknown task ID', function (): void {
    $tmpDir = sys_get_temp_dir() . '/php-agent-test-handler-' . uniqid();
    mkdir($tmpDir, 0755, true);

    $store = new \Xrea\Agent\Task\FileTaskStore($tmpDir);
    $handler = new ApiHandler(new CommandRunner(), taskStore: $store);

    $result = $handler->handle([
        'action' => 'task.status',
        'task_id' => 'non-existent-task-id-abc123',
    ]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('not found');

    // Cleanup
    system('rm -rf ' . escapeshellarg($tmpDir));
});
