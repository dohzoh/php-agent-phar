<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability\Process;

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\CommandRunner;
use Xrea\Agent\TaskResult;

final class RunSafeCapability implements CapabilityInterface
{
    /**
     * @param list<string> $allowedBinaries
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly bool $enabled = false,
        private readonly array $allowedBinaries = ['php', 'composer', 'git'],
        private readonly ?string $cwd = null,
    ) {
    }

    public function name(): string
    {
        return 'process.runSafe';
    }

    public function execute(array $input): TaskResult
    {
        if (!$this->enabled) {
            return TaskResult::error('Capability disabled: process.runSafe');
        }

        // Require argv as a list of strings
        if (!isset($input['argv']) || !is_array($input['argv'])) {
            return TaskResult::error('Missing required field: argv (must be an array)');
        }

        foreach ($input['argv'] as $arg) {
            if (!is_string($arg)) {
                return TaskResult::error('All argv entries must be strings');
            }
        }

        // Validate timeout is a positive integer if provided
        $timeout = isset($input['timeout']) ? (int) $input['timeout'] : null;
        if ($timeout !== null && $timeout <= 0) {
            return TaskResult::error('Timeout must be a positive integer');
        }

        // Validate argv with isSafeArgv using the instance's allowed binaries list
        if (!CommandRunner::isSafeArgv($input['argv'], $this->allowedBinaries)) {
            return TaskResult::error(
                'Unsafe command: binary not in allowed list or contains null bytes'
            );
        }

        // Execute the command array (no shell)
        return $this->runner->runArgv($input['argv'], $timeout, $this->cwd);
    }
}
