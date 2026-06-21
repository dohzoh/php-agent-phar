<?php

declare(strict_types=1);

namespace Xrea\Agent\Filesystem;

final class PathSandbox
{
    private string $resolvedWorkspace;

    public function __construct(
        private readonly string $workspace,
    ) {
        $this->resolvedWorkspace = realpath($this->workspace) ?: rtrim(realpath('.'), DIRECTORY_SEPARATOR);
    }

    public function workspace(): string
    {
        return $this->resolvedWorkspace;
    }

    /**
     * Resolves a path relative to the workspace.
     * Returns null if the resolved path is outside the workspace.
     */
    public function resolve(string $path): ?string
    {
        // Reject absolute paths that are not under workspace
        if ($path !== '' && $path[0] === DIRECTORY_SEPARATOR) {
            return null;
        }

        // Join resolved workspace with requested path
        if ($path === '') {
            $fullPath = $this->resolvedWorkspace;
        } else {
            $fullPath = rtrim($this->resolvedWorkspace, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
        }

        // If the target exists and is real, use realpath for safety (symlink resolution)
        if (file_exists($fullPath)) {
            $real = realpath($fullPath);
            if ($real === false) {
                return null;
            }
            $fullPath = $real;
        } else {
            // For non-existing paths, try to resolve the deepest existing ancestor
            $workspaceBase = rtrim($this->resolvedWorkspace, DIRECTORY_SEPARATOR);

            // Walk up from the target until we find an existing directory
            $candidate = dirname($fullPath);
            while (!is_dir($candidate) && $candidate !== $workspaceBase && strlen($candidate) > strlen($workspaceBase)) {
                $candidate = dirname($candidate);
            }

            if ($candidate === '' || realpath($candidate) === false) {
                return null;
            }

            // Verify the found ancestor is within workspace (exact match or prefix + separator)
            $realCandidate = rtrim(realpath($candidate), DIRECTORY_SEPARATOR);
            if (!$this->isInsideWorkspace(rtrim($workspaceBase, DIRECTORY_SEPARATOR), $realCandidate)) {
                return null;
            }

            // Reconstruct the full path from this known-good ancestor + remaining relative parts
            $relativeFromAncestor = str_replace(rtrim($candidate, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR, '', $fullPath);
            $fullPath = rtrim(realpath($candidate), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativeFromAncestor, DIRECTORY_SEPARATOR);

            // Final check: ensure the constructed path is within workspace base
            if (!$this->isInsideWorkspace(rtrim($workspaceBase, DIRECTORY_SEPARATOR), rtrim($fullPath, DIRECTORY_SEPARATOR))) {
                return null;
            }
        }

        // Final check: resolved path must be inside or equal to workspace (exact match OR prefix + separator)
        $finalCheck = rtrim($fullPath, DIRECTORY_SEPARATOR);
        if (!$this->isInsideWorkspace(rtrim($this->resolvedWorkspace, DIRECTORY_SEPARATOR), $finalCheck)) {
            return null;
        }

        // Symlink safety: verify the resolved path is still within workspace after resolution
        $realResolved = realpath($fullPath);
        if ($realResolved !== false) {
            if (!$this->isInsideWorkspace(rtrim($this->resolvedWorkspace, DIRECTORY_SEPARATOR), rtrim($realResolved, DIRECTORY_SEPARATOR))) {
                return null;
            }
            $fullPath = $realResolved;
        }

        return $fullPath;
    }

    /**
     * Check if \$path is inside or equal to \$workspaceBase.
     * Uses exact match OR prefix + DIRECTORY_SEPARATOR to prevent workspace escape (e.g., /tmp/base/ws2 escapes /tmp/base/ws).
     */
    private function isInsideWorkspace(string $workspaceBase, string $path): bool
    {
        return $path === $workspaceBase || strpos($path, $workspaceBase . DIRECTORY_SEPARATOR) === 0;
    }
}
