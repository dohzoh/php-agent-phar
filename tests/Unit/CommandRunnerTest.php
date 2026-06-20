<?php

declare(strict_types=1);

use Xrea\Agent\CommandRunner;

it('accepts commands whose binary is in the allow list', function (): void {
    expect(CommandRunner::isSafeCommand('pwd'))->toBeTrue()
        ->and(CommandRunner::isSafeCommand('/bin/pwd'))->toBeTrue()
        ->and(CommandRunner::isSafeCommand('git status'))->toBeTrue();
});

it('rejects commands whose binary is not in the allow list', function (): void {
    expect(CommandRunner::isSafeCommand('ssh example.com'))->toBeFalse()
        ->and(CommandRunner::isSafeCommand('bash -lc pwd'))->toBeFalse()
        ->and(CommandRunner::isSafeCommand(''))->toBeFalse();
});
