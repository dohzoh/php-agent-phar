<?php

declare(strict_types=1);

namespace Xrea\Agent\Task;

interface TaskStoreInterface
{
    public function create(string $command, ?string $id = null): void;

    public function recordResult(
        string $id,
        int $exitCode,
        string $output,
        float $duration,
        ?string $error = null,
    ): void;

    public function updateStatus(string $id, string $status): bool;

    public function get(string $id): ?TaskRecord;

    /** @return list<TaskRecord> */
    public function listAll(): array;
}
