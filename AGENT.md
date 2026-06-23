# AGENT.md

This repository contains a small PHP-based AI agent intended for use in an XREA-style hosting environment. Keep changes conservative: the project is currently a compact prototype with a small Pest test baseline and no installed PHP runtime in this workspace.

## Project Shape

- `bin/agent` is the CLI entry point.
- `public/index.php` is the HTTP JSON API entry point, dispatching to `ApiHandler`.
- `src/Agent.php` is a thin facade over config, REPL, and command execution.
- `src/ApiHandler.php` routes requests to actions: `ping`, `info`, `exec` (legacy), `task.execute`.
- `src/CommandRunner.php` handles allow-listed command execution (`run()`) and argv-based execution without shell (`runArgv()`).
- `src/TaskResult.php` is a structured result object with status, data, error, exitCode, duration, and toJson().
- `src/Capability/` contains the capability router pattern:
  - `CapabilityInterface.php` — contract for all capabilities.
  - `CapabilityRouter.php` — maps capability names to instances and dispatches execution.
  - `SystemInfoCapability.php` — returns PHP/environment info.
  - `Process/RunSafeCapability.php` — executes allow-listed binaries via argv array (no shell).
- `src/Filesystem/PathSandbox.php` validates paths against the configured workspace root.
- `src/Capability/Filesystem/` contains filesystem capabilities (`ListCapability`, `ReadCapability`, `WriteCapability`).
- `src/Task/` contains the task store:
  - `TaskRecord.php` — immutable task record DTO.
  - `TaskStoreInterface.php` — contract for task stores.
  - `FileTaskStore.php` — JSON-backed file store with auto-generated IDs.
- `src/Config/ConfigManager.php` loads and saves `~/.php-agent/config.json` by default.
- `src/AI/Client.php` calls an OpenAI-compatible chat completions endpoint via cURL.
- `src/Console/Repl.php` handles interactive and single-prompt AI usage.
- `build.php` and `stub.php` are for PHAR packaging.
- `Dockerfile` and `compose.yml` provide containerized serve/test workflows.
- `tests/` contains Pest tests for the current low-level behavior.

## Runtime Assumptions

- PHP `>=8.1` is required.
- Required extensions are `curl` and `phar`.
- Composer is expected for PSR-4 autoload generation.
- In this workspace, `php` and `composer` may not be installed, so verify locally before claiming runtime success.

## Useful Commands

```sh
composer install
php bin/agent --help
php bin/agent --init
php bin/agent --config-show
php bin/agent --exec "pwd"
php bin/agent -p "Hello"
composer serve
composer build
composer test
docker compose run --rm test
```

When PHP is available, run syntax checks before finishing PHP edits:

```sh
find . -path './vendor' -prune -o -name '*.php' -print -exec php -l {} \;
```

If Composer is available, also run:

```sh
composer validate --no-check-publish
composer test
```

## Safety Notes

- `CommandRunner::run()` (legacy exec) checks only the first command token against a static allow-list. This action is **disabled by default** (`worker.legacy_exec_enabled: false`).
- The legacy allow-list includes commands with broad effects such as `rm`, `mv`, `chmod`, `curl`, `wget`, `python`, `node`, `composer`, `npm`, and `pip`.
- `process.runSafe` uses `proc_open` with argv arrays (no shell), but is also **disabled by default**. When enabled, only binaries in the configured allow-list can run.
- Authentication via `Auth` class supports Bearer tokens from `Authorization` or `REDIRECT_HTTP_AUTHORIZATION` headers. Configure `worker.auth_token` to enforce it.
- CORS currently allows all origins when `worker.cors_allowed_origins` is set.

## Known Gaps

- Pest is configured under `tests/`, but coverage is still minimal (81 tests).
- `vendor/autoload.php` is required by the CLI and web entry points but is not tracked.
- PHAR packaging should be verified after Composer install; the current build layout may need adjustment so PSR-4 autoload paths work inside the archive.
- AI configuration supports OpenAI-compatible chat completions, but only the URL, API key, and model are currently configurable.
- `task.execute` requires a capability router to be configured at runtime (wired in `public/index.php`).
- PHAR packaging preserves full directory structure for PSR-4 autoload; verify after changes.

## Editing Guidance

- Prefer small, direct changes aligned with the existing simple class structure.
- Avoid adding frameworks unless the user explicitly asks for a larger redesign.
- Keep generated or local files such as `vendor/`, `build/`, and `*.phar` out of Git.
- If changing command execution, update both CLI behavior and HTTP API expectations.
- When adding new capabilities:
  1. Create the capability class implementing `CapabilityInterface`.
  2. Register it in the router (in code or via config wiring).
  3. Add Pest tests covering success and error paths.
  4. Update README.md documentation.
- If changing config keys, update `ConfigManager::getDefaults()`, `php-agent.example.json`, and `README.md` together.
