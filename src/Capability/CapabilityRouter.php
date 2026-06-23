<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability;

use Xrea\Agent\TaskResult;

class CapabilityRouter
{
    /** @var array<string, CapabilityInterface> */
    private array $capabilities = [];

    /**
     * @param iterable<CapabilityInterface> $capabilities
     */
    public function __construct(iterable $capabilities)
    {
        foreach ($capabilities as $capability) {
            $name = $capability->name();
            if (isset($this->capabilities[$name])) {
                throw new \InvalidArgumentException(
                    "Duplicate capability name: {$name}"
                );
            }
            $this->capabilities[$name] = $capability;
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(string $capability, array $input): TaskResult
    {
        if (!isset($this->capabilities[$capability])) {
            return TaskResult::error("Invalid capability: {$capability}");
        }

        return $this->capabilities[$capability]->execute($input);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->capabilities);
    }
}