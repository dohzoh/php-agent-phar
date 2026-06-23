<?php

declare(strict_types=1);

namespace Xrea\Agent\Filesystem;

final class PathSandbox
{
    private readonly string $resolvedWorkspace;

    public function __construct(
        private readonly string $workspace,
    ) {
        if (!file_exists($this->workspace)) {
            throw new \RuntimeException("Workspace does not exist: {$this->workspace}");
        }
        $real = realpath($this->workspace);
        if ($real === false) {
            throw new \RuntimeException("Cannot resolve workspace path: {$this->workspace}");
        }
        $this->resolvedWorkspace = rtrim($real, DIRECTORY_SEPARATOR);
    }

    public function workspace(): string
    {
        return $this->resolvedWorkspace;
    }

    public function resolve(string $path): ?string
    {
        if ($path !== '' && $path[0] === DIRECTORY_SEPARATOR) {
            return null;
        }

        if ($path === '') {
            return $this->resolvedWorkspace;
        }

        $fullPath = $this->resolvedWorkspace . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);

        if (file_exists($fullPath)) {
            $real = realpath($fullPath);
            if ($real === false) {
                return null;
            }
            if (!$this->isInsideWorkspace($this->resolvedWorkspace, rtrim($real, DIRECTORY_SEPARATOR))) {
                return null;
            }
            return $real;
        }

        $relativePath = ltrim($path, DIRECTORY_SEPARATOR);

        if ($this->pathEscapesWorkspace($relativePath)) {
            return null;
        }

        $normalizedRelative = $this->normalizePath($relativePath);

        if ($normalizedRelative === '') {
            return $this->resolvedWorkspace;
        }

        $normalizedFull = $this->resolvedWorkspace . DIRECTORY_SEPARATOR . $normalizedRelative;

        if (!$this->isInsideWorkspace($this->resolvedWorkspace, rtrim($normalizedFull, DIRECTORY_SEPARATOR))) {
            return null;
        }

        return $normalizedFull;
    }

    private function normalizePath(string $path): string
    {
        $parts = explode(DIRECTORY_SEPARATOR, $path);
        $resolved = [];
        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            } elseif ($part === '..') {
                if (count($resolved) > 0) {
                    array_pop($resolved);
                }
            } else {
                $resolved[] = $part;
            }
        }
        return implode(DIRECTORY_SEPARATOR, $resolved);
    }

    private function isInsideWorkspace(string $workspaceBase, string $path): bool
    {
        return $path === $workspaceBase
            || strpos($path, $workspaceBase . DIRECTORY_SEPARATOR) === 0;
    }

    private function pathEscapesWorkspace(string $relativePath): bool
    {
        $parts = explode(DIRECTORY_SEPARATOR, $relativePath);
        $stack = [];

        foreach ($parts as $part) {
            if ($part === '' || $part === '.') {
                continue;
            } elseif ($part === '..') {
                if (empty($stack)) {
                    return true;
                }
                array_pop($stack);
                $reconstructed = implode(DIRECTORY_SEPARATOR, $stack);
                if ($reconstructed !== '') {
                    $physicalPath = $this->resolvedWorkspace . DIRECTORY_SEPARATOR . $reconstructed;
                    if (!is_dir($physicalPath)) {
                        return true;
                    }
                }
            } else {
                $stack[] = $part;
            }
        }

        return false;
    }
}
