<?php

declare(strict_types=1);

namespace Xrea\Agent\Task;

final class TaskRecord
{
    public function __construct(
        private readonly string $id,
        private readonly string $status = 'pending',
        private readonly ?string $command = null,
        private readonly ?int $timeout = null,
        private readonly ?string $error = null,
        private readonly string|null $output = null,
        private readonly int|null $exitCode = null,
        private readonly float|null $duration = null,
        private readonly ?\DateTimeImmutable $createdAt = null,
        private readonly ?\DateTimeImmutable $completedAt = null,
    ) {
    }

    public function id(): string
    {
        return $this->id;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function command(): ?string
    {
        return $this->command;
    }

    public function timeout(): ?int
    {
        return $this->timeout;
    }

    public function error(): ?string
    {
        return $this->error;
    }

    public function output(): string|null
    {
        return $this->output;
    }

    public function exitCode(): int|null
    {
        return $this->exitCode;
    }

    public function duration(): float|null
    {
        return $this->duration;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt ?? new \DateTimeImmutable();
    }

    public function completedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) ($data['id'] ?? ''),
            status: (string) ($data['status'] ?? 'pending'),
            command: isset($data['command']) && $data['command'] !== '' ? (string) $data['command'] : null,
            timeout: isset($data['timeout']) && $data['timeout'] !== '' ? (int) $data['timeout'] : null,
            error: isset($data['error']) && $data['error'] !== '' ? (string) $data['error'] : null,
            output: isset($data['output']) && $data['output'] !== '' ? (string) $data['output'] : null,
            exitCode: isset($data['exit_code']) && $data['exit_code'] !== '' ? (int) $data['exit_code'] : null,
            duration: isset($data['duration']) && $data['duration'] !== '' ? (float) $data['duration'] : null,
            createdAt: self::parseDateField($data['created_at'] ?? null),
            completedAt: self::parseDateField($data['completed_at'] ?? null),
        );
    }

    /** @param mixed $value */
    private static function parseDateField(mixed $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'command' => $this->command,
            'timeout' => $this->timeout,
            'error' => $this->error,
            'output' => $this->output,
            'exit_code' => $this->exitCode,
            'duration' => $this->duration,
            'created_at' => $this->createdAt()->format('Y-m-d H:i:s'),
            'completed_at' => $this->completedAt?->format('Y-m-d H:i:s'),
        ];
    }
}
