<?php

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

    public function run(string $command, ?int $timeout = null): TaskResult
    {
        $timeout = min($timeout ?? self::MAX_EXECUTION_TIME, self::MAX_EXECUTION_TIME);

        $command = $this->validate($command);
        $start = microtime(true);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

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

        $duration = microtime(true) - $start;

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
                $stderr ?: "Command failed with exit code $exitCode",
                $exitCode,
                $duration
            );
        }

        return TaskResult::success($stdout, $exitCode, $duration);
    }

    private function validate(string $command): string
    {
        $command = trim($command);
        if ($command === '') {
            throw new \InvalidArgumentException('Command must not be empty');
        }

        return $command;
    }

    public static function isSafeCommand(string $command): bool
    {
        $tokens = preg_split('/\s+/', trim($command));
        if (empty($tokens)) {
            return false;
        }

        $binary = $tokens[0];
        $base = basename($binary);

        return in_array($base, self::SAFE_COMMANDS, true);
    }
}
