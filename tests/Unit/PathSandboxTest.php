<?php

declare(strict_types=1);

use Xrea\Agent\Filesystem\PathSandbox;

it('resolves workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->workspace())->toBe(realpath($dir));

    rmdir($dir);
});

it('accepts relative path inside workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    file_put_contents("{$dir}/test.txt", 'hello');

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('test.txt'))->toBe(realpath("{$dir}/test.txt"));

    unlink("{$dir}/test.txt");
    rmdir($dir);
});

it('rejects ../outside path', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('../outside'))->toBeNull();

    rmdir($dir);
});

it('handles non-existing write target with existing parent', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/sub", 0755, true);

    $sandbox = new PathSandbox($dir);

    $resolved = $sandbox->resolve('sub/newfile.txt');

    expect($resolved)->not->toBeNull()
        ->and(str_contains((string) $resolved, 'sub'))->toBeTrue();

    rmdir("{$dir}/sub");
    rmdir($dir);
});

it('rejects non-existing intermediate with .. traversal', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('nonexistent/../../outside/file.txt'))->toBeNull();

    rmdir($dir);
});

it('rejects multiple .. traversals through non-existing intermediates', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('a/b/c/../../../../outside/file.txt'))->toBeNull();

    rmdir($dir);
});

it('allows existing intermediate with .. that stays inside', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/subdir", 0755, true);

    $sandbox = new PathSandbox($dir);

    $resolved = $sandbox->resolve('subdir/../sibling/newfile.txt');

    expect($resolved)->not->toBeNull()
        ->and(str_starts_with((string) $resolved, realpath($dir)));

    rmdir("{$dir}/subdir");
    rmdir($dir);
});

it('rejects existing subdir with .. going above workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/subdir", 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('subdir/../../outside/file.txt'))->toBeNull();

    rmdir("{$dir}/subdir");
    rmdir($dir);
});

it('accepts redundant leading dots', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    file_put_contents("{$dir}/test.txt", 'hello');

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('./././test.txt'))->toBe(realpath("{$dir}/test.txt"));

    unlink("{$dir}/test.txt");
    rmdir($dir);
});

it('throws on non-existing workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));

    expect(fn () => new PathSandbox($dir))
        ->toThrow(\RuntimeException::class, 'Workspace does not exist');
});

it('resolves empty path to workspace root', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve(''))->toBe(realpath($dir));

    rmdir($dir);
});

it('rejects absolute paths', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('/etc/passwd'))->toBeNull();

    rmdir($dir);
});

it('normalizes .. within existing intermediates correctly', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/a", 0755, true);
    mkdir("{$dir}/a/b", 0755, true);

    $sandbox = new PathSandbox($dir);

    $resolved = $sandbox->resolve('a/b/../c.txt');
    expect($resolved)->not->toBeNull()
        ->and(str_ends_with((string) $resolved, '/a/c.txt'))->toBeTrue();

    rmdir("{$dir}/a/b");
    rmdir("{$dir}/a");
    rmdir($dir);
});
