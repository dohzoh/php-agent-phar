<?php

declare(strict_types=1);

use Xrea\Agent\Filesystem\PathSandbox;

it('resolves workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->workspace())->toBe(realpath($dir));

    // Cleanup
    rmdir($dir);
});

it('accepts relative path inside workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    file_put_contents("{$dir}/test.txt", 'hello');

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('test.txt'))->toBe(realpath("{$dir}/test.txt"));

    // Cleanup
    unlink("{$dir}/test.txt");
    rmdir($dir);
});

it('rejects ../outside path', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('../outside'))->toBeNull();

    // Cleanup
    rmdir($dir);
});

it('handles non-existing write target with existing parent', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/sub", 0755, true);

    $sandbox = new PathSandbox($dir);

    // sub/newfile.txt does not exist yet, but parent 'sub' exists
    $resolved = $sandbox->resolve('sub/newfile.txt');

    expect($resolved)->not->toBeNull()
        ->and(str_contains((string) $resolved, 'sub'))->toBeTrue();

    // Cleanup
    rmdir("{$dir}/sub");
    rmdir($dir);
});