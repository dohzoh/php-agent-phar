<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability\Filesystem;

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\Filesystem\PathSandbox;
use Xrea\Agent\TaskResult;

final class ReadCapability implements CapabilityInterface
{
    public function __construct(
        private readonly PathSandbox $sandbox,
        private readonly int $maxBytes = 1048576,
    ) {
    }

    public function name(): string
    {
        return 'fs.read';
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): TaskResult
    {
        if (!isset($input['path']) || !is_string($input['path'])) {
            return TaskResult::error('Missing required field: path');
        }

        $resolved = $this->sandbox->resolve($input['path']);
        if ($resolved === null) {
            return TaskResult::error('Path is outside workspace');
        }

        if (!is_file($resolved)) {
            return TaskResult::error("Not a file: {$input['path']}");
        }

        if (!is_readable($resolved)) {
            return TaskResult::error("File not readable: {$input['path']}");
        }

        $size = filesize($resolved);
        if ($size === false) {
            return TaskResult::error('Failed to determine file size');
        }

        if ($size > $this->maxBytes) {
            return TaskResult::error("File too large: {$input['path']} ({$size} bytes, max {$this->maxBytes})");
        }

        $content = file_get_contents($resolved);
        if ($content === false) {
            return TaskResult::error('Failed to read file');
        }

        // Compute relative path for response
        $relativePath = ltrim(str_replace(rtrim($this->sandbox->workspace(), DIRECTORY_SEPARATOR), '', $resolved), DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            $relativePath = basename($resolved);
        }

        return TaskResult::success([
            'path' => $relativePath,
            'content' => $content,
            'bytes' => strlen($content),
        ]);
    }
}