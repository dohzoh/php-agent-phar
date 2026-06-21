<?php

declare(strict_types=1);

use Xrea\Agent\Task\TaskRecord;

it('creates with default status', function (): void {
    $record = new TaskRecord(id: 'test-001');

    expect($record->id())->toBe('test-001')
        ->and($record->status())->toBe('pending');
});

it('creates with custom status and fields', function (): void {
    $createdAt = new \DateTimeImmutable();
    $completedAt = new \DateTimeImmutable();

    $record = new TaskRecord(
        id: 'test-002',
        status: 'completed',
        command: 'php -v',
        timeout: 30,
        error: null,
        output: 'PHP 8.1.0',
        exitCode: 0,
        duration: 0.5,
        createdAt: $createdAt,
        completedAt: $completedAt,
    );

    expect($record->id())->toBe('test-002')
        ->and($record->status())->toBe('completed')
        ->and($record->command())->toBe('php -v')
        ->and($record->timeout())->toBe(30)
        ->and($record->output())->toBe('PHP 8.1.0')
        ->and($record->exitCode())->toBe(0)
        ->and($record->duration())->toBe(0.5)
        ->and($record->createdAt()->format('c'))->toBe($createdAt->format('c'))
        ->and($record->completedAt()->format('c'))->toBe($completedAt->format('c'));
});

it('defaults createdAt to now when not provided', function (): void {
    $beforeCreate = microtime(true);
    $record = new TaskRecord(id: 'test-003');
    $afterCreate = microtime(true);

    expect($record->createdAt()->getTimestamp())
        ->toBeGreaterThanOrEqual((int) $beforeCreate)
        ->and($record->createdAt()->getTimestamp())
        ->toBeLessThanOrEqual((int) $afterCreate);
});

it('defaults completedAt to null when not provided', function (): void {
    $record = new TaskRecord(id: 'test-004');

    expect($record->completedAt())->toBeNull();
});

it('serializes toArray correctly', function (): void {
    $createdAt = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', '2024-01-15 10:30:00');

    $record = new TaskRecord(
        id: 'test-005',
        status: 'running',
        command: 'composer install',
        timeout: 60,
        createdAt: $createdAt,
    );

    $data = $record->toArray();

    expect($data['id'])->toBe('test-005')
        ->and($data['status'])->toBe('running')
        ->and($data['command'])->toBe('composer install')
        ->and($data['timeout'])->toBe(60)
        ->and($data['error'])->toBeNull()
        ->and($data['output'])->toBeNull()
        ->and($data['exit_code'])->toBeNull()
        ->and($data['duration'])->toBeNull()
        ->and($data['created_at'])->toBe('2024-01-15 10:30:00')
        ->and($data['completed_at'])->toBeNull();
});

it('deserializes fromArray correctly', function (): void {
    $record = TaskRecord::fromArray([
        'id' => 'test-006',
        'status' => 'failed',
        'command' => 'php -r "exit(1);"',
        'timeout' => 30,
        'error' => 'Command failed',
        'output' => '',
        'exit_code' => 1,
        'duration' => 2.5,
    ]);

    expect($record->id())->toBe('test-006')
        ->and($record->status())->toBe('failed')
        ->and($record->command())->toBe('php -r "exit(1);"')
        ->and($record->timeout())->toBe(30)
        ->and($record->error())->toBe('Command failed')
        ->and($record->output())->toBeNull()
        ->and($record->exitCode())->toBe(1)
        ->and($record->duration())->toBe(2.5);
});

it('handles empty string fields as null', function (): void {
    $record = TaskRecord::fromArray([
        'id' => 'test-007',
        'status' => 'completed',
        'error' => '',
        'output' => '',
    ]);

    expect($record->error())->toBeNull()
        ->and($record->output())->toBeNull();
});

it('handles missing optional fields gracefully', function (): void {
    $record = TaskRecord::fromArray(['id' => 'test-008']);

    expect($record->status())->toBe('pending')
        ->and($record->command())->toBeNull()
        ->and($record->timeout())->toBeNull()
        ->and($record->error())->toBeNull();
});

it('round-trips through toArray/fromArray', function (): void {
    $original = new TaskRecord(
        id: 'test-009',
        status: 'completed',
        command: 'ls -la',
        timeout: 10,
        output: 'file1 file2',
        exitCode: 0,
        duration: 0.3,
    );

    $serialized = $original->toArray();
    $restored = TaskRecord::fromArray($serialized);

    expect($restored->id())->toBe('test-009')
        ->and($restored->status())->toBe('completed')
        ->and($restored->command())->toBe('ls -la')
        ->and($restored->timeout())->toBe(10)
        ->and($restored->output())->toBe('file1 file2')
        ->and($restored->exitCode())->toBe(0)
        ->and(abs($restored->duration() - $original->duration()))->toBeLessThan(0.001);
});