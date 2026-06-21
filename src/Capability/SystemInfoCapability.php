<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability;

use Xrea\Agent\TaskResult;

final class SystemInfoCapability implements CapabilityInterface
{
    public function name(): string
    {
        return 'system.info';
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): TaskResult
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
}