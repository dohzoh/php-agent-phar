<?php

declare(strict_types=1);

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\Capability\CapabilityRouter;
use Xrea\Agent\Capability\SystemInfoCapability;
use Xrea\Agent\TaskResult;

it('executes known capability', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);

    $result = $router->execute('system.info', []);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data))->toBeTrue();
});

it('rejects unknown capability', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);

    $result = $router->execute('unknown.capability', []);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('Invalid capability: unknown.capability');
});

it('reports names in registration order', function (): void {
    $router = new CapabilityRouter([new SystemInfoCapability()]);

    expect($router->names())->toEqual(['system.info']);
});

it('rejects duplicate capability names', function (): void {
    new class implements CapabilityInterface {
        public function name(): string { return 'system.info'; }
        public function execute(array $input): TaskResult { return TaskResult::success([]); }
    };
    expect(function () {
        new CapabilityRouter([
            new SystemInfoCapability(),
            new class implements CapabilityInterface {
                public function name(): string { return 'system.info'; }
                public function execute(array $input): TaskResult { return TaskResult::success([]); }
            },
        ]);
    })->toThrow(InvalidArgumentException::class, 'Duplicate capability name: system.info');
});