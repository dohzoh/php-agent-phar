<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability\Filesystem;

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\Filesystem\PathSandbox;
use Xrea\Agent\TaskResult;

final class WriteCapability implements CapabilityInterface
{
    public function __construct(
        private readonly PathSandbox $sandbox,
        private readonly int $maxBytes = 1048576,
    ) {
    }

    public function name(): string
    {
        return 'fs.write';
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): TaskResult
    {
        if (!isset($input['path']) || !is_string($input['path'])) {
            return TaskResult::error('Missing required field: path');
        }

        if (!array_key_exists('content', $input) || !is_string($input['content'])) {
            return TaskResult::error('Missing required field: content');
        }

        // Check content size before writing
        $contentBytes = strlen($input['content']);
        if ($contentBytes > $this->maxBytes) {
            return TaskResult::error("Content too large ({$contentBytes} bytes, max {$this->maxBytes})");
        }

        $resolved = $this->sandbox->resolve($input['path']);
        if ($resolved === null) {
            return TaskResult::error('Path is outside workspace');
        }

        // Ensure parent directory exists and is inside workspace
        $parentDir = dirname($resolved);
        if (!is_dir($parentDir)) {
            // Check that the parent path is within the workspace before creating it
            $workspace = rtrim($this->sandbox->workspace(), DIRECTORY_SEPARATOR);
            $candidateParent = rtrim($parentDir, DIRECTORY_SEPARATOR);

            if (strpos($candidateParent, $workspace) !== 0) {
                return TaskResult::error('Path is outside workspace');
            }

            if (!mkdir($parentDir, 0755, true)) {
                return TaskResult::error('Failed to create parent directory');
            }
        }

        // Write with LOCK_EX for atomicity
        $bytes = file_put_contents($resolved, $input['content'], LOCK_EX);
        if ($bytes === false) {
            return TaskResult::error('Failed to write file');
        }

        // Compute relative path for response
        $relativePath = ltrim(str_replace(rtrim($this->sandbox->workspace(), DIRECTORY_SEPARATOR), '', $resolved), DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            $relativePath = basename($resolved);
        }

        return TaskResult::success([
            'path' => $relativePath,
            'bytes' => $bytes,
        ]);
    }
}