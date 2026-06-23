# 01 Fix PathSandbox

## Context

Files to modify or create:

- Modify `src/Filesystem/PathSandbox.php`
- Modify `tests/Unit/PathSandboxTest.php` (add regression tests)
- Modify `tests/Unit/FilesystemCapabilityTest.php` (add regression tests)

Related existing code:

- `PathSandbox::resolve()` at `src/Filesystem/PathSandbox.php:26-93`
- `PathSandbox::isInsideWorkspace()` at `src/Filesystem/PathSandbox.php:99-102`
- `PathSandbox::__construct()` at `src/Filesystem/PathSandbox.php:11-15` (workspace fallback)
- Tests: `tests/Unit/PathSandboxTest.php`, `tests/Unit/FilesystemCapabilityTest.php`

## Bug: non-existing intermediate + `..` traversal

### Root Cause

`resolve()` の非既存パスブランチでは、祖先探索が workspace 自身で止まった後、`str_replace()` で再構成したパスに `..` が残留する。このパスを `isInsideWorkspace()` の prefix 比較に通すと、workspace で始まる文字列として誤って許可される。

### Example

Workspace `/tmp/base/ws`, path `nonexistent/../../outside/secret.txt`:

```
Step                         | Path                                   | Result
-----------------------------|----------------------------------------|-------
fullPath join                | /tmp/base/ws/nonexistent/../../outside/secret.txt
file_exists                  | /tmp/base/outside/secret.txt           | false
ancestor walk -> candidate   | /tmp/base/ws                           | is_dir: true
realpath(candidate)          | /tmp/base/ws                           | ok
reconstructed fullPath       | /tmp/base/ws/../../outside/secret.txt  | ← BUG
isInsideWorkspace(ws, path)  | prefix match /tmp/base/ws/ → true      | ← BUG
realpath(final)              | /tmp/base/outside/secret.txt           | false (nonexistent missing)
symlink check                | skipped (realpath was false)           |
RETURN                       | /tmp/base/ws/../../outside/secret.txt  | ← ALLOWED
```

## Implementation Steps

### 1. Fix constructor — no silent CWD fallback

Current (buggy):
```php
$this->resolvedWorkspace = realpath($this->workspace) ?: rtrim(realpath('.'), DIRECTORY_SEPARATOR);
```

Fix: throw if workspace does not resolve:
```php
$real = realpath($this->workspace);
if ($real === false) {
    throw new \RuntimeException("Workspace does not exist: {$this->workspace}");
}
$this->resolvedWorkspace = rtrim($real, DIRECTORY_SEPARATOR);
```

Rationale: workspace が存在しない場合、CWD にフォールバックすると意図しないディレクトリを root として扱う危険がある。早期に例外を投げる。

### 2. Add `normalizePath()` helper

Add a private method that resolves `..` and `.` in a path string WITHOUT touching the filesystem:

```php
/**
 * Resolve . and .. segments in a path string without filesystem access.
 */
private function normalizePath(string $path): string
{
    $parts = explode(DIRECTORY_SEPARATOR, $path);
    $resolved = [];
    foreach ($parts as $part) {
        if ($part === '..') {
            if (count($resolved) > 0) {
                array_pop($resolved);
            }
        } elseif ($part !== '.' && $part !== '') {
            $resolved[] = $part;
        }
    }
    $result = implode(DIRECTORY_SEPARATOR, $resolved);
    // Preserve leading slash for absolute paths
    if ($path !== '' && $path[0] === DIRECTORY_SEPARATOR) {
        $result = DIRECTORY_SEPARATOR . $result;
    }
    return $result;
}
```

Edge case `count($resolved) === 0` when popping: if path goes above root like `../../../etc`, just continue (can't pop past root).

### 3. Apply normalization before all `isInsideWorkspace()` checks

In `resolve()` method, before EVERY `isInsideWorkspace()` call, normalize the path:

```php
// Before line 63:
if (!$this->isInsideWorkspace(rtrim($workspaceBase, DIRECTORY_SEPARATOR), $realCandidate)) {

// Before line 72 (reconstruction result):
$normalized = $this->normalizePath($fullPath);
if (!$this->isInsideWorkspace(rtrim($workspaceBase, DIRECTORY_SEPARATOR), rtrim($normalized, DIRECTORY_SEPARATOR))) {

// Before line 79 (final check):
$normalized = $this->normalizePath($fullPath);
if (!$this->isInsideWorkspace(rtrim($this->resolvedWorkspace, DIRECTORY_SEPARATOR), rtrim($normalized, DIRECTORY_SEPARATOR))) {

// Before line 86 (symlink check):
$realResolved = realpath($fullPath);
if ($realResolved !== false) {
    if (!$this->isInsideWorkspace(rtrim($this->resolvedWorkspace, DIRECTORY_SEPARATOR), rtrim($realResolved, DIRECTORY_SEPARATOR))) {
```

The symlink check (line 86) already uses `realpath()` which resolves `..` natively, so it does NOT need normalization.

But for lines 72 and 79 (the non-existing path branches), `normalizePath()` is essential.

### 4. Also normalize at the symlink fallback

Line 83-90: if `realpath()` fails (non-existing target), the symlink check is skipped. We should also normalize the path for the general return, though the safety check is already done at lines 72/79 with normalization.

## Edge Cases to Cover

| Path | Workspace | Expected | Reason |
|------|-----------|----------|--------|
| `file.txt` | `/ws` | ALLOWED | simple relative path |
| `subdir/file.txt` | `/ws` | ALLOWED | nested inside |
| `../outside/file.txt` | `/ws` | REJECTED | direct sibling |
| `nonexistent/../../outside/file.txt` | `/ws` | REJECTED | non-existing intermediate + .. |
| `a/b/c/../../../../outside/file.txt` | `/ws` | REJECTED | deep non-existing + multiple .. |
| `subdir/../sibling/newfile.txt` | `/ws` | ALLOWED | existing subdir, .. cancels, sibling inside ws |
| `subdir/../../outside/file.txt` | `/ws` | REJECTED | existing subdir, goes above ws |
| `./././file.txt` | `/ws` | ALLOWED | redundant dots |
| empty string `""` | `/ws` | ALLOWED (returns ws) | workspace itself |

## Constraints

- Do not change the method signature of `resolve()` or `isInsideWorkspace()`.
- Do not add filesystem access in `normalizePath()`.
- Do not break the existing test suite.
- Keep `DIRECTORY_SEPARATOR` usage for cross-platform compatibility.

## Tests

### PathSandboxTest — Add these test cases:

```php
it('rejects non-existing intermediate + .. traversal', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('nonexistent/../../outside/file.txt'))->toBeNull();

    rmdir($dir);
});

it('rejects deep non-existing + multiple .. traversal', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);

    $sandbox = new PathSandbox($dir);

    expect($sandbox->resolve('a/b/c/../../../../outside/file.txt'))->toBeNull();

    rmdir($dir);
});

it('accepts existing subdir with .. cancelling inside workspace', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/subdir", 0755, true);

    $sandbox = new PathSandbox($dir);

    // subdir/../ cancels to nothing, so path is sibling/newfile.txt inside ws
    $resolved = $sandbox->resolve('subdir/../sibling/newfile.txt');
    expect($resolved)->not->toBeNull()
        ->and(str_contains((string) $resolved, 'sibling'))->toBeTrue();

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
```

### FilesystemCapabilityTest — Add:

```php
it('rejects read via non-existing intermediate + .. traversal', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/../outside-dir", 0755, true);
    file_put_contents("{$dir}/../outside-dir/secret.txt", 'leaked');

    $sandbox = new PathSandbox($dir);
    $capability = new ReadCapability($sandbox);

    $result = $capability->execute(['path' => 'nonexistent/../../outside-dir/secret.txt']);

    expect($result->status)->toBe('error');
    // Should fail at sandbox level, not "Not a file"
    expect($result->error)->toContain('outside workspace');

    unlink("{$dir}/../outside-dir/secret.txt");
    rmdir("{$dir}/../outside-dir");
    rmdir($dir);
});

it('rejects write via non-existing intermediate + .. traversal', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    mkdir($dir, 0755, true);
    mkdir("{$dir}/../outside-dir", 0755, true);

    $sandbox = new PathSandbox($dir);
    $capability = new WriteCapability($sandbox);

    $result = $capability->execute([
        'path' => 'nonexistent/../../outside-dir/pwned.txt',
        'content' => 'malicious',
    ]);

    expect($result->status)->toBe('error');
    expect($result->error)->toContain('outside workspace');

    // Verify no file was written outside
    expect(file_exists("{$dir}/../outside-dir/pwned.txt"))->toBeFalse();

    rmdir("{$dir}/../outside-dir");
    rmdir($dir);
});
```

## Verification

After implementation, run:

```sh
docker compose run --rm test
docker compose run --rm test sh -lc "git config --global --add safe.directory /app && composer validate --no-check-publish"
docker compose run --rm test sh -lc "find . -path ./vendor -prune -o -name '*.php' -print -exec php -l {} \;"
docker compose run --rm test sh -lc "composer build && php php-agent.phar --help"
```
