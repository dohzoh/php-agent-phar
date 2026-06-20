<?php

declare(strict_types=1);

use Xrea\Agent\TaskResult;

it('serializes successful results', function (): void {
    $result = TaskResult::success('ok', 0, 0.12345)->toArray();

    expect($result)->toMatchArray([
        'status' => 'success',
        'data' => 'ok',
        'error' => null,
        'exit_code' => 0,
        'duration' => 0.1235,
    ]);
});

it('serializes error results', function (): void {
    $json = TaskResult::error('failed', 2)->toJson();

    expect($json)->toBe('{"status":"error","data":null,"error":"failed","exit_code":2}');
});
