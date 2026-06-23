<?php

declare(strict_types=1);

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\Capability\Filesystem\ListCapability;
use Xrea\Agent\Capability\Filesystem\ReadCapability;
use Xrea\Agent\Capability\Filesystem\WriteCapability;
use Xrea\Agent\Filesystem\PathSandbox;
use Xrea\Agent\TaskResult;

// Helper to create a test sandbox in a temp directory
function createTestSandbox(string $basePath, string $workspaceName = 'ws'): PathSandbox
{
    $dir = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $workspaceName;
    mkdir($dir, 0755, true);
    return new PathSandbox($dir);
}

it('fs.list returns files and directories', function (): void {
    $sandbox = createTestSandbox(sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4)), 'list-ws');
    file_put_contents("{$sandbox->workspace()}/alpha.txt", 'a');
    mkdir("{$sandbox->workspace()}/beta_dir");

    $cap = new ListCapability($sandbox);
    $result = $cap->execute(['path' => '.']);

    expect($result->status)->toBe('success')
        ->and(is_array($result->data['entries']))
        ->and(count($result->data['entries']))->toBe(2)
        ->and($result->data['path'])->not->toBeNull();

    // Cleanup
    unlink("{$sandbox->workspace()}/alpha.txt");
    rmdir("{$sandbox->workspace()}/beta_dir");
});

it('fs.read reads a file', function (): void {
    $sandbox = createTestSandbox(sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4)), 'read-ws');
    file_put_contents("{$sandbox->workspace()}/hello.txt", 'Hello World!');

    $cap = new ReadCapability($sandbox, 1048576);
    $result = $cap->execute(['path' => 'hello.txt']);

    expect($result->status)->toBe('success')
        ->and($result->data['content'])->toBe('Hello World!')
        ->and($result->data['bytes'])->toBe(12);

    // Cleanup
    unlink("{$sandbox->workspace()}/hello.txt");
});

it('fs.read rejects oversized files', function (): void {
    $sandbox = createTestSandbox(sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4)), 'read-big-ws');
    file_put_contents("{$sandbox->workspace()}/big.txt", str_repeat('x', 100));

    // Use a small maxBytes limit (50 bytes) to trigger size rejection
    $cap = new ReadCapability($sandbox, 50);
    $result = $cap->execute(['path' => 'big.txt']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('File too large');

    // Cleanup
    unlink("{$sandbox->workspace()}/big.txt");
});

it('fs.write writes content', function (): void {
    $sandbox = createTestSandbox(sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4)), 'write-ws');

    $cap = new WriteCapability($sandbox, 1048576);
    $result = $cap->execute(['path' => 'newfile.txt', 'content' => 'Hello!']);

    expect($result->status)->toBe('success')
        ->and($result->data['bytes'])->toBe(6)
        ->and(file_get_contents("{$sandbox->workspace()}/newfile.txt"))->toBe('Hello!');

    // Cleanup
    unlink("{$sandbox->workspace()}/newfile.txt");
});

it('fs.write creates parent directories', function (): void {
    $sandbox = createTestSandbox(sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4)), 'write-deep-ws');

    $cap = new WriteCapability($sandbox, 1048576);
    $result = $cap->execute(['path' => 'a/b/c/deep.txt', 'content' => 'deep content']);

    expect($result->status)->toBe('success')
        ->and($result->data['bytes'])->toBe(12)
        ->and(file_get_contents("{$sandbox->workspace()}/a/b/c/deep.txt"))->toBe('deep content');

    // Cleanup
    unlink("{$sandbox->workspace()}/a/b/c/deep.txt");
    rmdir("{$sandbox->workspace()}/a/b/c");
    rmdir("{$sandbox->workspace()}/a/b");
    rmdir("{$sandbox->workspace()}/a");
});

it('filesystem capabilities reject outside workspace paths', function (): void {
    $sandbox = createTestSandbox(sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4)), 'security-ws');

    // ListCapability rejects ../outside
    mkdir("{$sandbox->workspace()}/ok");
    expect((new ListCapability($sandbox))->execute(['path' => '../outside'])->status)->toBe('error')
        ->and((new ListCapability($sandbox))->execute(['path' => '../outside'])->error)->toContain('outside workspace');

    // ReadCapability rejects ../etc/passwd
    file_put_contents("{$sandbox->workspace()}/t.txt", 'x');
    expect((new ReadCapability($sandbox))->execute(['path' => '../etc/passwd'])->status)->toBe('error')
        ->and((new ReadCapability($sandbox))->execute(['path' => '../etc/passwd'])->error)->toContain('outside workspace');

    // WriteCapability rejects ../bad
    expect((new WriteCapability($sandbox))->execute(['path' => '../other/bad', 'content' => 'x'])->status)->toBe('error')
        ->and((new WriteCapability($sandbox))->execute(['path' => '../other/bad', 'content' => 'x'])->error)->toContain('outside workspace');

    // Cleanup
    unlink("{$sandbox->workspace()}/t.txt");
    rmdir("{$sandbox->workspace()}/ok");
});

it('rejects read via non-existing intermediate + .. traversal', function (): void {
    $base = sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4));
    mkdir("{$base}/ws", 0755, true);
    mkdir("{$base}/outside-dir", 0755, true);
    file_put_contents("{$base}/outside-dir/secret.txt", 'leaked');

    $sandbox = new PathSandbox("{$base}/ws");
    $capability = new ReadCapability($sandbox, 1048576);

    $result = $capability->execute(['path' => 'nonexistent/../../outside-dir/secret.txt']);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('outside workspace');

    unlink("{$base}/outside-dir/secret.txt");
    rmdir("{$base}/outside-dir");
    rmdir("{$base}/ws");
});

it('rejects write via non-existing intermediate + .. traversal', function (): void {
    $base = sys_get_temp_dir() . '/php-agent-test-fs-' . bin2hex(random_bytes(4));
    mkdir("{$base}/ws", 0755, true);
    mkdir("{$base}/outside-dir", 0755, true);

    $sandbox = new PathSandbox("{$base}/ws");
    $capability = new WriteCapability($sandbox, 1048576);

    $result = $capability->execute([
        'path' => 'nonexistent/../../outside-dir/pwned.txt',
        'content' => 'malicious',
    ]);

    expect($result->status)->toBe('error')
        ->and($result->error)->toContain('outside workspace')
        ->and(file_exists("{$base}/outside-dir/pwned.txt"))->toBeFalse();

    rmdir("{$base}/outside-dir");
    rmdir("{$base}/ws");
});