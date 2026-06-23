<?php

declare(strict_types=1);

use Xrea\Agent\Capability\Process\RunSafeCapability;
use Xrea\Agent\CommandRunner;

test('disabled capability returns error', function (): void {
    $runner = new CommandRunner();
    $capability = new RunSafeCapability($runner, enabled: false);

    $result = $capability->execute(['argv' => ['php', '-v']]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('disabled');
});

test('missing argv returns error', function (): void {
    $runner = new CommandRunner();
    $capability = new RunSafeCapability($runner, enabled: true);

    $result = $capability->execute([]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('argv');
});

test('non-array argv returns error', function (): void {
    $runner = new CommandRunner();
    $capability = new RunSafeCapability($runner, enabled: true);

    $result = $capability->execute(['argv' => 'not-an-array']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('argv');
});

test('disallowed binary returns error', function (): void {
    $runner = new CommandRunner();
    $capability = new RunSafeCapability($runner, enabled: true);

    $result = $capability->execute(['argv' => ['ssh', 'example.com']]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('not in allowed list');
});

test('enabled capability can run a harmless command', function (): void {
    $runner = new CommandRunner();
    $capability = new RunSafeCapability(
        $runner,
        enabled: true,
        allowedBinaries: ['php'],
        cwd: null,
    );

    $result = $capability->execute([
        'argv' => ['php', '-r', 'echo "ok";'],
        'timeout' => 5,
    ]);

    expect($result->status)->toBe('success')
        ->and(trim($result->data ?? ''))->toBe('ok');
});
