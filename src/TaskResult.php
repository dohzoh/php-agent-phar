<?php

namespace Xrea\Agent;

class TaskResult
{
    public function __construct(
        public readonly string $status,
        public readonly mixed $data = null,
        public readonly ?string $error = null,
        public readonly ?int $exitCode = null,
        public readonly ?float $duration = null,
    ) {
    }

    public static function success(mixed $data, ?int $exitCode = 0, ?float $duration = null): self
    {
        return new self('success', $data, null, $exitCode, $duration);
    }

    public static function error(string $message, ?int $exitCode = 1, ?float $duration = null): self
    {
        return new self('error', null, $message, $exitCode, $duration);
    }

    public function toArray(): array
    {
        $result = [
            'status' => $this->status,
            'data' => $this->data,
            'error' => $this->error,
            'exit_code' => $this->exitCode,
        ];

        if ($this->duration !== null) {
            $result['duration'] = round($this->duration, 4);
        }

        return $result;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
