<?php

declare(strict_types=1);

namespace Xrea\Agent;

class CommandRunner
{
    private const SAFE_COMMANDS = [
        'ls', 'cat', 'echo', 'pwd', 'id', 'whoami', 'uname',
        'php', 'python3', 'python', 'node',
        'cp', 'mv', 'rm', 'mkdir', 'chmod', 'touch',
        'git', 'composer', 'npm', 'pip',
        'date', 'du', 'df', 'wc', 'head', 'tail', 'sort', 'grep',
        'which', 'curl', 'wget',
    ];

    private const MAX_EXECUTION_TIME = 25;

    /**
     * @deprecated Use runArgv() or capability-based execution instead.
     */
    public function run(string $command, ?int $timeout = null): TaskResult
    {
        $timeout = min($timeout ?? self::MAX_EXECUTION_TIME, self::MAX_EXECUTION_TIME);
        $command = trim($command);
        if ($command === '') {
            throw new \InvalidArgumentException('Command must not be empty');
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $startTime = microtime(true);
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            return TaskResult::error('Failed to start process');
        }

        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $running = true;
        $stopTime = microtime(true) + $timeout;

        while ($running && microtime(true) < $stopTime) {
            $streams = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            if (stream_select($streams, $write, $except, 0, 200000) > 0) {
                foreach ($streams as $stream) {
                    $data = fread($stream, 8192);
                    if ($data === false || $data === '') {
                        $running = false;
                        break;
                    }
                    if ($stream === $pipes[1]) {
                        $stdout .= $data;
                    } else {
                        $stderr .= $data;
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $running = false;
                if ($status['exitcode'] !== -1) {
                    $remainingStdout = stream_get_contents($pipes[1]);
                    $remainingStderr = stream_get_contents($pipes[2]);
                    $stdout .= $remainingStdout !== false ? $remainingStdout : '';
                    $stderr .= $remainingStderr !== false ? $remainingStderr : '';
                }
            }
        }

        $duration = microtime(true) - $startTime;

        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($running) {
            proc_terminate($process, 9);
            proc_close($process);
            return TaskResult::error('Command timed out', 124, $duration);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return TaskResult::error(
                $stderr ?: "Command failed with exit code {$exitCode}",
                $exitCode,
                $duration
            );
        }

        return TaskResult::success($stdout, $exitCode, $duration);
    }

    public static function isSafeCommand(string $command): bool
    {
        $tokens = preg_split('/\s+/', trim($command));

        if (empty($tokens)) {
            return false;
        }

        // Reject null bytes in any token
        foreach ($tokens as $token) {
            if (strpos($token, "\0") !== false) {
                return false;
            }
        }

        return in_array(basename((string) $tokens[0]), self::SAFE_COMMANDS, true);
    }

    /**
     * @param list<string>  $argv
     * @param non-empty-list<string> $allowedBinaries
     */
    public static function isSafeArgv(array $argv, array $allowedBinaries = ['php', 'composer', 'git']): bool
    {
        if (empty($argv)) {
            return false;
        }

        // Reject null bytes in any argument and non-string entries
        foreach ($argv as $arg) {
            if (!is_string($arg)) {
                return false;
            }
            if (strpos($arg, "\0") !== false) {
                return false;
            }
        }

        // Reject non-string first element (e.g. null)
        $binary = basename((string) $argv[0]);

        return in_array($binary, $allowedBinaries, true);
    }

    /**
     * Execute a command using an argv array (bypasses the shell).
     *
     * @param list<string> $argv
     */
    public function runArgv(array $argv, ?int $timeout = null, ?string $cwd = null): TaskResult
    {
        if (empty($argv)) {
            return TaskResult::error('argv must not be empty');
        }

        foreach ($argv as $arg) {
            if (!is_string($arg)) {
                return TaskResult::error('All argv entries must be strings');
            }
        }

        $timeout = min($timeout ?? self::MAX_EXECUTION_TIME, self::MAX_EXECUTION_TIME);

        // Optional cwd validation
        if ($cwd !== null) {
            if (!is_dir($cwd)) {
                return TaskResult::error("Working directory does not exist: {$cwd}");
            }
        }

        $startTime = microtime(true);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($argv, $descriptors, $pipes, $cwd ?? null);

        if (!is_resource($process)) {
            return TaskResult::error('Failed to start process');
        }

        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $running = true;
        $stopTime = microtime(true) + $timeout;

        while ($running && microtime(true) < $stopTime) {
            $streams = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            if (stream_select($streams, $write, $except, 0, 200000) > 0) {
                foreach ($streams as $stream) {
                    $data = fread($stream, 8192);
                    if ($data === false || $data === '') {
                        $running = false;
                        break;
                    }
                    if ($stream === $pipes[1]) {
                        $stdout .= $data;
                    } else {
                        $stderr .= $data;
                    }
                }
            }

            $status = proc_get_status($process);
            if (!$status['running']) {
                $running = false;
                if ($status['exitcode'] !== -1) {
                    $remainingStdout = stream_get_contents($pipes[1]);
                    $remainingStderr = stream_get_contents($pipes[2]);
                    $stdout .= $remainingStdout !== false ? $remainingStdout : '';
                    $stderr .= $remainingStderr !== false ? $remainingStderr : '';
                }
            }
        }

        $duration = microtime(true) - $startTime;

        fclose($pipes[1]);
        fclose($pipes[2]);

        if ($running) {
            proc_terminate($process, 9);
            proc_close($process);
            return TaskResult::error('Command timed out', 124, $duration);
        }

        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            return TaskResult::error(
                $stderr ?: "Command failed with exit code {$exitCode}",
                $exitCode,
                $duration
            );
        }

        return TaskResult::success($stdout, $exitCode, $duration);
    }
}
