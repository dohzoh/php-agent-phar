<?php

declare(strict_types=1);

use Xrea\Agent\Task\FileTaskStore;
use Xrea\Agent\Task\TaskRecord;

beforeEach(function (): void {
    $this->tmpDir = sys_get_temp_dir() . '/php-agent-test-tasks-' . uniqid();
    mkdir($this->tmpDir, 0755, true);
});

afterEach(function (): void {
    if (is_dir($this->tmpDir)) {
        system('rm -rf ' . escapeshellarg($this->tmpDir));
    }
});

it('creates a task record', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('php -v', 'task-001');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    $record = $store->get('task-001');

    expect($record)->toBeInstanceOf(TaskRecord::class)
        ->and($record->id())->toBe('task-001')
        ->and($record->status())->toBe('pending')
        ->and($record->command())->toBe('php -v');
});

it('throws on invalid task ID', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    expect(fn () => $store->create('php -v', 'invalid id!'))->toThrow(\InvalidArgumentException::class, 'must only contain alphanumeric');
});

it('records a result and marks completed', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('git status', 'task-002');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    try {
        $store->recordResult('task-002', 0, 'on branch main', 1.5);
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    $record = $store->get('task-002');

    expect($record->status())->toBe('completed')
        ->and($record->exitCode())->toBe(0)
        ->and($record->output())->toBe('on branch main')
        ->and($record->duration())->toBe(1.5);
});

it('records result for non-existent task', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        // recordResult(id, exitCode, output, duration, ?error)
        $store->recordResult('nonexistent', 1, 'some output', 0.1, 'error msg');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    $record = $store->get('nonexistent');

    expect($record)->toBeInstanceOf(TaskRecord::class)
        ->and($record->status())->toBe('completed')
        ->and($record->exitCode())->toBe(1)
        ->and($record->output())->toBe('some output')
        ->and($record->error())->toBe('error msg');
});

it('updates status to running', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('composer install', 'task-003');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    expect($store->updateStatus('task-003', 'running'))->toBeTrue();

    $record = $store->get('task-003');
    expect($record->status())->toBe('running');
});

it('updates status to failed with completedAt set', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('php bad-command.php', 'task-004');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    expect($store->updateStatus('task-004', 'failed'))->toBeTrue();

    $record = $store->get('task-004');
    expect($record->status())->toBe('failed')
        ->and($record->completedAt())->not->toBeNull();
});

it('returns false for get on non-existent task', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    expect($store->get('nonexistent'))->toBeNull();
});

it('rejects invalid status value in updateStatus', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('php -v', 'task-013');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    expect(fn () => $store->updateStatus('task-013', 'invalid_status'))
        ->toThrow(\InvalidArgumentException::class, 'Invalid status: invalid_status');
});

it('validates task ID format in all operations', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    expect(fn () => $store->create('', '../etc/passwd'))
        ->toThrow(\InvalidArgumentException::class, 'must only contain alphanumeric');

    expect(fn () => $store->recordResult('bad id!', 0, '', 0.0))
        ->toThrow(\InvalidArgumentException::class, 'must only contain alphanumeric');

    expect(fn () => $store->updateStatus('path/traversal', 'running'))
        ->toThrow(\InvalidArgumentException::class, 'must only contain alphanumeric');
});

it('creates task with auto-generated ID when no ID provided', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('php -v'); // no explicit ID — should auto-generate
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    // Get all records to find the one we just created
    $records = $store->listAll();
    expect(count($records))->toBe(1);

    $recordData = $records[0];
    expect(is_string($recordData['id']))->toBeTrue();
    expect(strlen((string) $recordData['id']))->toBe(32);
    // Verify it's lowercase hex only (no uppercase, no hyphens/underscores)
    expect(preg_match('/^[a-f0-9]{32}$/', (string) $recordData['id']))->toBe(1);

    // Can retrieve the record via its generated ID
    $retrieved = $store->get((string) $recordData['id']);
    expect($retrieved)->not->toBeNull()
        ->and($retrieved->command())->toBe('php -v')
        ->and($retrieved->status())->toBe('pending');
});

it('generates unique IDs for each auto-created task', function (): void {
    $store = new FileTaskStore($this->tmpDir);

    try {
        $store->create('cmd one');
        $store->create('cmd two');
        $store->create('cmd three');
    } catch (\Throwable $e) {
        throw new \RuntimeException('Should not have thrown: ' . $e->getMessage(), 0, $e);
    }

    $records = $store->listAll();
    expect(count($records))->toBe(3);

    // All IDs should be unique and valid hex format
    $ids = array_column($records, 'id');
    expect(array_unique($ids))->toHaveCount(3);
    foreach ($ids as $id) {
        expect(preg_match('/^[a-f0-9]{32}$/', (string) $id))->toBe(1);
    }
});
