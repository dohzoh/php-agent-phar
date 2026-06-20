# 02 Capability Router

## Context

Files to modify or create:

- Add `src/Capability/CapabilityInterface.php`
- Add `src/Capability/CapabilityRouter.php`
- Add `src/Capability/SystemInfoCapability.php`
- Modify `src/ApiHandler.php`
- Modify `src/Agent.php` if constructor wiring changes
- Update tests under `tests/Unit`

Related existing code:

- `ApiHandler::handle()` currently supports `ping`, `info`, and `exec`.
- `handleInfo()` is private inside `ApiHandler`.
- `TaskResult` is the standard return object.

## Input / Output

Create interface:

```php
namespace Xrea\Agent\Capability;

use Xrea\Agent\TaskResult;

interface CapabilityInterface
{
    public function name(): string;

    /**
     * @param array<string, mixed> $input
     */
    public function execute(array $input): TaskResult;
}
```

Create router:

```php
namespace Xrea\Agent\Capability;

use Xrea\Agent\TaskResult;

class CapabilityRouter
{
    /**
     * @param iterable<CapabilityInterface> $capabilities
     */
    public function __construct(iterable $capabilities);

    /**
     * @param array<string, mixed> $input
     */
    public function execute(string $capability, array $input): TaskResult;

    /**
     * @return list<string>
     */
    public function names(): array;
}
```

Modify `ApiHandler` constructor to accept router:

```php
public function __construct(
    private readonly CommandRunner $runner,
    private readonly ?CapabilityRouter $capabilityRouter = null,
) {
}
```

New API action:

```json
{
  "action": "task.execute",
  "capability": "system.info",
  "input": {}
}
```

## Implementation Steps

1. Create `CapabilityInterface`.
2. Create `CapabilityRouter`.
   - Store capabilities in associative array keyed by `name()`.
   - Reject duplicate names with `InvalidArgumentException`.
   - `execute()` returns `TaskResult::error("Invalid capability: $capability")` if unknown.
3. Move current system info logic into `SystemInfoCapability`.
   - Capability name: `system.info`.
   - Return same data shape as current `info`.
4. Modify `ApiHandler`:
   - Valid actions: `ping`, `info`, `exec`, `task.execute`.
   - Keep existing `info` action as backward-compatible wrapper.
   - For `task.execute`, require non-empty string `capability`.
   - `input` must be an array; default to `[]` only when missing.
   - If router is missing, return `TaskResult::error('Capability router is not configured')`.
5. Update HTTP wiring in `public/index.php` to pass a router with at least `SystemInfoCapability`.
6. Update CLI `Agent::runCommand()` only if needed to preserve existing `--exec`.

## Constraints

- Keep `exec` action temporarily for backward compatibility. It will be restricted in ToDo 04.
- Do not add filesystem capabilities in this step.
- Do not add async behavior in this step.
- `TaskResult` remains unchanged unless absolutely necessary.

## Tests

Add tests:

- `CapabilityRouterTest`
  - executes known capability,
  - rejects unknown capability,
  - reports names sorted or insertion order consistently,
  - rejects duplicate capability names.
- `ApiHandlerTest`
  - `task.execute` requires `capability`,
  - `task.execute` rejects non-array `input`,
  - `task.execute` can call `system.info`,
  - existing `ping` still works,
  - existing `info` still works.
