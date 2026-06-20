# 03 Filesystem Capabilities

## Context

Files to modify or create:

- Add `src/Filesystem/PathSandbox.php`
- Add `src/Capability/Filesystem/ListCapability.php`
- Add `src/Capability/Filesystem/ReadCapability.php`
- Add `src/Capability/Filesystem/WriteCapability.php`
- Update `public/index.php` router wiring
- Update tests under `tests/Unit`
- Update `README.md`

Related existing code:

- The current `exec` path allows shell commands such as `ls`, `cat`, `mkdir`, and `touch`.
- The reconstructed plan requires native PHP filesystem operations instead of shelling out.
- `ConfigManager` should provide `worker.workspace`.

## Input / Output

Create class:

```php
namespace Xrea\Agent\Filesystem;

final class PathSandbox
{
    public function __construct(
        private readonly string $workspace,
    ) {
    }

    public function workspace(): string;

    public function resolve(string $path): ?string;
}
```

Capabilities:

```php
namespace Xrea\Agent\Capability\Filesystem;

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\Filesystem\PathSandbox;
use Xrea\Agent\TaskResult;

final class ListCapability implements CapabilityInterface
{
    public function __construct(private readonly PathSandbox $sandbox) {}
    public function name(): string; // "fs.list"
    public function execute(array $input): TaskResult;
}

final class ReadCapability implements CapabilityInterface
{
    public function __construct(private readonly PathSandbox $sandbox, private readonly int $maxBytes = 1048576) {}
    public function name(): string; // "fs.read"
    public function execute(array $input): TaskResult;
}

final class WriteCapability implements CapabilityInterface
{
    public function __construct(private readonly PathSandbox $sandbox, private readonly int $maxBytes = 1048576) {}
    public function name(): string; // "fs.write"
    public function execute(array $input): TaskResult;
}
```

Request examples:

```json
{"action":"task.execute","capability":"fs.list","input":{"path":"."}}
{"action":"task.execute","capability":"fs.read","input":{"path":"README.md"}}
{"action":"task.execute","capability":"fs.write","input":{"path":"tmp/out.txt","content":"hello\n"}}
```

Response data:

`fs.list`:

```php
[
    'path' => 'relative/path',
    'entries' => [
        ['name' => 'README.md', 'type' => 'file', 'size' => 123],
    ],
]
```

`fs.read`:

```php
[
    'path' => 'README.md',
    'content' => '...',
    'bytes' => 123,
]
```

`fs.write`:

```php
[
    'path' => 'tmp/out.txt',
    'bytes' => 6,
]
```

## Implementation Steps

1. Implement `PathSandbox`.
   - Resolve configured workspace to a real absolute path.
   - Return `null` if workspace cannot be resolved.
   - For requested paths, reject empty string only where capability requires it.
   - Join relative path to workspace.
   - Use `realpath()` for existing paths.
   - For non-existing write targets, resolve the parent directory and append basename.
   - Reject paths outside workspace.
2. Implement `fs.list`.
   - `path` defaults to `.`.
   - Require resolved path to be a directory.
   - Return sorted entries by name.
   - Include `type`: `file`, `dir`, or `other`.
   - Include `size` for files.
3. Implement `fs.read`.
   - Require `path`.
   - Require resolved path to be a readable file.
   - Reject file larger than `$maxBytes`.
   - Return raw string content.
4. Implement `fs.write`.
   - Require `path` and `content`.
   - `content` must be string.
   - Reject content larger than `$maxBytes`.
   - Create parent directories with `mkdir($dir, 0755, true)` if needed, but only inside workspace.
   - Write with `file_put_contents(..., LOCK_EX)`.
5. Wire capabilities in `public/index.php` using `worker.workspace`.
6. Update README with capability examples.

## Constraints

- Do not call shell commands.
- Do not allow absolute paths outside workspace.
- Do not follow symlinks outside workspace.
- Do not silently truncate reads or writes.
- Keep max size configurable by constructor for tests.
- Default max size: 1 MiB.

## Tests

Add tests:

- `PathSandboxTest`
  - resolves workspace,
  - accepts relative path inside workspace,
  - rejects `../outside`,
  - handles non-existing write target with existing parent.
- `FilesystemCapabilityTest`
  - `fs.list` returns files and directories,
  - `fs.read` reads a file,
  - `fs.read` rejects oversized files,
  - `fs.write` writes content,
  - `fs.write` creates parent directories,
  - all capabilities reject outside workspace paths.
