<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability;

use Xrea\Agent\TaskResult;

interface CapabilityInterface
{
    public function name(): string;

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): TaskResult;
}