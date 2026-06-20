# 04 Process Runner Policy

## Context

Files to modify or create:

- Modify `src/CommandRunner.php`
- Add `src/Capability/Process/RunSafeCapability.php`
- Update `src/ApiHandler.php`
- Update `public/index.php`
- Update tests under `tests/Unit`
- Update `README.md` and `AGENT.md`

Related existing code:

- `CommandRunner::isSafeCommand()` only checks the first token.
- `ApiHandler` currently exposes action `exec`.
- `docs/reconsidered_plan.md` states raw shell should be optional and disabled by default.

## Input / Output

Preferred `CommandRunner` API addition:

```php
namespace Xrea\Agent;

class CommandRunner
{
    /**
     * @param list<string> $argv
     * @param array<string, string> $env
     */
    public function runArgv(array $argv, ?int $timeout = null, ?string $cwd = null, array $env = []): TaskResult;

    /**
     * @param list<string> $argv
     * @param list<string> $allowedBinaries
     */
    public static function isSafeArgv(array $argv, array $allowedBinaries): bool;
}
```

Create capability:

```php
namespace Xrea\Agent\Capability\Process;

use Xrea\Agent\Capability\CapabilityInterface;
use Xrea\Agent\CommandRunner;
use Xrea\Agent\TaskResult;

final class RunSafeCapability implements CapabilityInterface
{
    /**
     * @param list<string> $allowedBinaries
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly bool $enabled,
        private readonly array $allowedBinaries = ['php', 'composer', 'git'],
        private readonly ?string $cwd = null,
    ) {
    }

    public function name(): string; // "process.runSafe"
    public function execute(array $input): TaskResult;
}
```

Request shape:

```json
{
  "action": "task.execute",
  "capability": "process.runSafe",
  "input": {
    "argv": ["php", "-v"],
    "timeout": 5
  }
}
```

## Implementation Steps

1. Add `CommandRunner::runArgv()`.
   - Require non-empty argv.
   - Require every arg to be string.
   - Use `proc_open` with argv array if supported in PHP 8.1.
   - Avoid shell string concatenation.
   - Respect max timeout of 25 seconds.
   - Support optional cwd only when directory exists.
2. Add `CommandRunner::isSafeArgv()`.
   - Reject empty argv.
   - First item basename must be in `$allowedBinaries`.
   - Reject argv entries containing null bytes.
3. Keep existing `run(string $command)` temporarily for CLI/backward compatibility, but avoid using it from HTTP capabilities.
4. Add `RunSafeCapability`.
   - If disabled, return `TaskResult::error('Capability disabled: process.runSafe')`.
   - Require `argv` as list of strings.
   - Optional `timeout` integer.
   - Validate with `isSafeArgv`.
5. Update `ApiHandler::handleExec()`.
   - For HTTP/API path, return `TaskResult::error('Action disabled: exec')` unless an explicit config flag enables legacy exec.
   - Simpler acceptable approach: keep CLI using `Agent::runCommand()` directly but stop exposing `exec` in HTTP docs.
6. Wire `process.runSafe` in `public/index.php` using config:
   - `worker.capabilities.process.runSafe.enabled`
   - optional `worker.capabilities.process.runSafe.allowed_binaries`
7. Update docs to say raw `exec` is legacy and not recommended.

## Constraints

- Do not use `shell_exec`.
- Do not build a shell command with `implode`.
- Do not enable `process.runSafe` by default.
- Keep timeout hard cap at 25 seconds.
- Do not include network tools in default allowed binaries.

## Tests

Add tests:

- `CommandRunnerTest`
  - `isSafeArgv` accepts `['php', '-v']`,
  - rejects empty argv,
  - rejects disallowed binary,
  - rejects null byte args.
- `RunSafeCapabilityTest`
  - disabled capability returns error,
  - missing argv returns error,
  - disallowed binary returns error,
  - enabled capability can run a harmless command such as `php -r 'echo "ok";'` in Docker.
- `ApiHandlerTest`
  - legacy `exec` behavior is either explicitly disabled or covered by config-based test.
