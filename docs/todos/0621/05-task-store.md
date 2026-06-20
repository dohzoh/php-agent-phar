# 05 Task Store

## Context

Files to modify or create:

- Add `src/Task/TaskRecord.php`
- Add `src/Task/TaskStoreInterface.php`
- Add `src/Task/FileTaskStore.php`
- Modify `src/ApiHandler.php`
- Update `ConfigManager` defaults if needed
- Update tests under `tests/Unit`
- Update `README.md`

Related existing code:

- Current API is synchronous only.
- `docs/reconsidered_plan.md` recommends task IDs and status documents for XREA's short execution model.
- This step should add a simple file-backed status store, not a background worker.

## Input / Output

Create record:

```php
namespace Xrea\Agent\Task;

final class TaskRecord
{
    /**
     * @param array<string, mixed> $request
     * @param mixed $result
     */
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly array $request,
        public readonly mixed $result,
        public readonly ?string $error,
        public readonly string $createdAt,
        public readonly string $updatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
```

Create interface:

```php
namespace Xrea\Agent\Task;

interface TaskStoreInterface
{
    /**
     * @param array<string, mixed> $request
     * @param mixed $result
     */
    public function saveCompleted(array $request, mixed $result, ?string $error = null): TaskRecord;

    public function get(string $id): ?TaskRecord;
}
```

Create file store:

```php
namespace Xrea\Agent\Task;

final class FileTaskStore implements TaskStoreInterface
{
    public function __construct(private readonly string $directory) {}
    public function saveCompleted(array $request, mixed $result, ?string $error = null): TaskRecord;
    public function get(string $id): ?TaskRecord;
}
```

New API actions:

```json
{"action":"task.execute","capability":"fs.list","input":{"path":"."},"record":true}
{"action":"task.status","task_id":"<id>"}
```

## Implementation Steps

1. Implement `TaskRecord`.
   - Use ISO 8601 timestamps via `date(DATE_ATOM)`.
2. Implement `FileTaskStore`.
   - Create directory if missing with `0700`.
   - Generate IDs with `bin2hex(random_bytes(16))`.
   - Save JSON files as `<id>.json`.
   - Use `JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT`.
   - Reject IDs that are not lowercase hex length 32.
3. Modify `ApiHandler` constructor:

```php
public function __construct(
    private readonly CommandRunner $runner,
    private readonly ?CapabilityRouter $capabilityRouter = null,
    private readonly ?TaskStoreInterface $taskStore = null,
) {
}
```

4. For `task.execute`:
   - Execute capability synchronously.
   - If request has `"record": true` and store is configured, save result.
   - Return data:

```php
[
    'task_id' => $record->id,
    'result' => $taskResult->toArray(),
]
```

   - If no recording requested, keep returning the capability `TaskResult` directly.
5. Add `task.status`.
   - Require `task_id`.
   - Return `TaskResult::success($record->toArray())`.
   - Unknown ID returns `TaskResult::error('Task not found')`.
6. Wire store in `public/index.php`.
   - Suggested config default: `worker.task_store.path` set to `<workspace>/.php-agent-tasks`.
   - It is acceptable to instantiate only when path is configured.

## Constraints

- Do not implement background jobs yet.
- Do not use a database in this step.
- Do not store auth headers or secrets in task request records.
- Keep task files inside configured workspace or a configured safe path.
- IDs must not be user-controlled file paths.

## Tests

Add tests:

- `FileTaskStoreTest`
  - saves and reads completed records,
  - rejects invalid IDs by returning `null`,
  - creates storage directory.
- `ApiHandlerTest`
  - `task.execute` with `record: true` returns task ID and result,
  - `task.status` returns saved record,
  - `task.status` missing ID returns error,
  - unknown task ID returns error.
