<?php

declare(strict_types=1);

use Xrea\Agent\ApiHandler;
use Xrea\Agent\CommandRunner;

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

it('requires a command for exec', function (): void {
    $handler = new ApiHandler(new CommandRunner());

    $result = $handler->handle(['action' => 'exec']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toBe('Command is required');
});
