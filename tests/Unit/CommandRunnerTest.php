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

it('accepts argv whose binary is in the allow list', function (): void {
    expect(CommandRunner::isSafeArgv(['php', '-v']))->toBeTrue()
        ->and(CommandRunner::isSafeArgv(['git', 'status']))->toBeTrue()
        ->and(CommandRunner::isSafeArgv(['/usr/bin/git', 'log']))->toBeTrue();
});

it('rejects empty argv', function (): void {
    expect(CommandRunner::isSafeArgv([]))->toBeFalse();
});

it('rejects disallowed binary in argv', function (): void {
    expect(CommandRunner::isSafeArgv(['ssh', 'example.com']))->toBeFalse()
        ->and(CommandRunner::isSafeArgv(['/usr/bin/perl', '-e', 'print 1;']))->toBeFalse();
});

it('rejects argv entries containing null bytes', function (): void {
    expect(CommandRunner::isSafeArgv(["php\0-v"]))->toBeFalse()
        ->and(CommandRunner::isSafeArgv(['git', "status\0fake"]))->toBeFalse();
});

it('accepts custom allowedBinaries parameter', function (): void {
    // Custom whitelist with 'ls' and 'cat' instead of defaults
    expect(CommandRunner::isSafeArgv(['ls', '-la'], ['ls']))->toBeTrue()
        ->and(CommandRunner::isSafeArgv(['cat', '/etc/passwd'], ['ls']))->toBeFalse();
});

it('defaults to php,composer,git when allowedBinaries omitted', function (): void {
    // These should use the default allowlist
    expect(CommandRunner::isSafeArgv(['php', '-r', 'echo 1;']))->toBeTrue()
        ->and(CommandRunner::isSafeArgv(['composer', 'install']))->toBeTrue()
        ->and(CommandRunner::isSafeArgv(['git', 'status']))->toBeTrue();

    // These should be rejected by default allowlist
    expect(CommandRunner::isSafeArgv(['ls', '-la']))->toBeFalse()
        ->and(CommandRunner::isSafeArgv(['curl', 'http://example.com']))->toBeFalse();
});

test('runArgv executes a harmless command successfully', function (): void {
    $runner = new CommandRunner();
    $result = $runner->runArgv(['php', '-r', 'echo "argv-ok";'], timeout: 5);

    expect($result->status)->toBe('success')
        ->and(trim($result->data ?? ''))->toBe('argv-ok');
});

test('runArgv rejects empty argv', function (): void {
    $runner = new CommandRunner();
    $result = $runner->runArgv([]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('empty');
});
