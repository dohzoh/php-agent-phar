<?php

declare(strict_types=1);

namespace Xrea\Agent\Task;

final class FileTaskStore implements TaskStoreInterface
{
    private readonly string $dir;

    /** @var array<string, TaskRecord> */
    private array $cache = [];

    public function __construct(
        private readonly string $baseDir,
    ) {
        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0755, true);
        }

        $resolved = realpath($this->baseDir);
        $this->dir = $resolved ?: rtrim($this->baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'tasks';
    }

    public function create(string $command, ?string $id = null): void
    {
        if ($id === null) {
            // Generate secure hex ID (32 lowercase hex chars)
            $id = bin2hex(random_bytes(16));
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new \InvalidArgumentException('Task ID must only contain alphanumeric characters, hyphens, and underscores');
        }

        $record = new TaskRecord(
            id: $id,
            status: 'pending',
            command: $command,
            createdAt: new \DateTimeImmutable(),
        );

        $this->save($record);
    }

    public function recordResult(
        string $id,
        int $exitCode,
        string $output,
        float $duration,
        ?string $error = null,
    ): void {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new \InvalidArgumentException('Task ID must only contain alphanumeric characters, hyphens, and underscores');
        }

        $existing = $this->get($id);
        if ($existing !== null) {
            $record = new TaskRecord(
                id: $existing->id(),
                status: 'completed',
                command: $existing->command(),
                timeout: $existing->timeout(),
                error: $error,
                output: $output !== '' ? $output : null,
                exitCode: $exitCode,
                duration: $duration,
                createdAt: $existing->createdAt(),
                completedAt: new \DateTimeImmutable(),
            );
        } else {
            $record = new TaskRecord(
                id: $id,
                status: 'completed',
                command: null,
                timeout: null,
                error: $error,
                output: $output !== '' ? $output : null,
                exitCode: $exitCode,
                duration: $duration,
                completedAt: new \DateTimeImmutable(),
            );
        }

        $this->save($record);
    }

    public function updateStatus(string $id, string $status): bool
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            throw new \InvalidArgumentException('Task ID must only contain alphanumeric characters, hyphens, and underscores');
        }

        $record = $this->get($id);
        if ($record === null) {
            return false;
        }

        $validStatuses = ['pending', 'running', 'completed', 'failed'];
        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException("Invalid status: {$status}");
        }

        $record = new TaskRecord(
            id: $record->id(),
            status: $status,
            command: $record->command(),
            timeout: $record->timeout(),
            error: $record->error(),
            output: $record->output(),
            exitCode: $record->exitCode(),
            duration: $record->duration(),
            createdAt: $record->createdAt(),
            completedAt: ($status === 'completed' || $status === 'failed') ? new \DateTimeImmutable() : null,
        );

        $this->save($record);
        return true;
    }

    public function get(string $id): ?TaskRecord
    {
        if (isset($this->cache[$id])) {
            return $this->cache[$id];
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $id)) {
            return null;
        }

        $file = $this->dir . DIRECTORY_SEPARATOR . "{$id}.json";
        if (!is_file($file)) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        // Handle both formats: [ {...} ] or { ... }
        $recordData = match (true) {
            isset($data['id']) => $data,
            !empty($data) && is_array(reset($data)) => reset($data),
            default => null,
        };

        if ($recordData === null || !is_array($recordData)) {
            return null;
        }

        $record = TaskRecord::fromArray($recordData);
        $this->cache[$id] = $record;
        return $record;
    }

    public function listAll(): array
    {
        $this->cache = [];

        $records = [];
        if (!is_dir($this->dir)) {
            return $records;
        }

        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.json');
        if ($files === false) {
            return $records;
        }

        foreach ($files as $file) {
            $basename = basename($file);
            if (!preg_match('/^[a-zA-Z0-9_-]+\.json$/', $basename)) {
                continue;
            }

            $id = substr($basename, 0, -5);
            $record = $this->get($id);
            if ($record !== null) {
                $records[] = $record->toArray();
            }
        }

        return $records;
    }

    private function save(TaskRecord $record): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $record->id())) {
            throw new \InvalidArgumentException('Task ID must only contain alphanumeric characters, hyphens, and underscores');
        }

        $file = $this->dir . DIRECTORY_SEPARATOR . "{$record->id()}.json";
        $data = [$record->toArray()];

        if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
            throw new \RuntimeException("Failed to write task record: {$record->id()}");
        }

        $this->cache[$record->id()] = $record;
    }
}
