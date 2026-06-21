<?php

declare(strict_types=1);

namespace Xrea\Agent\Capability\Filesystem;

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\Filesystem\PathSandbox;
use Xrea\Agent\TaskResult;

final class ListCapability implements CapabilityInterface
{
    public function __construct(
        private readonly PathSandbox $sandbox,
    ) {
    }

    public function name(): string
    {
        return 'fs.list';
    }

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): TaskResult
    {
        $path = $input['path'] ?? '.';
        if (!is_string($path)) {
            return TaskResult::error('Missing required field: path');
        }

        $resolved = $this->sandbox->resolve($path);
        if ($resolved === null) {
            return TaskResult::error('Path is outside workspace');
        }

        if (!is_dir($resolved)) {
            return TaskResult::error("Not a directory: {$input['path']}");
        }

        $entries = [];
        $items = scandir($resolved);
        if ($items === false) {
            return TaskResult::error('Failed to read directory');
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $fullPath = rtrim($resolved, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $item;
            $type = 'other';
            if (is_dir($fullPath)) {
                $type = 'dir';
            } elseif (is_file($fullPath)) {
                $type = 'file';
            }

            $entry = ['name' => $item, 'type' => $type];
            if ($type === 'file') {
                $size = filesize($fullPath);
                $entry['size'] = $size !== false ? $size : 0;
            }

            $entries[] = $entry;
        }

        // Sort by name
        usort($entries, static function (array $a, array $b): int {
            return strcmp($a['name'], $b['name']);
        });

        // Compute relative path from workspace for response
        $relativePath = ltrim(str_replace(rtrim($this->sandbox->workspace(), DIRECTORY_SEPARATOR), '', rtrim($resolved, DIRECTORY_SEPARATOR)), DIRECTORY_SEPARATOR);
        if ($relativePath === '') {
            $relativePath = '.';
        }

        return TaskResult::success([
            'path' => $relativePath,
            'entries' => $entries,
        ]);
    }
}