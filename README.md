# PHP Agent

PHP Agent is a small PHP-based AI assistant and command runner designed for constrained hosting environments such as XREA. It provides:

- a CLI REPL and single-prompt mode,
- a minimal HTTP JSON API with capability-based execution,
- an OpenAI-compatible chat completions client,
- optional PHAR packaging.

The project is currently a prototype. Review the safety notes before exposing it to a network or untrusted users.

## Requirements

- PHP `>=8.1`
- PHP extensions: `curl`, `phar`
- Composer

Install dependencies:

```sh
composer install
```

Or use Docker:

```sh
docker compose run --rm test
```

## Configuration

Create the default config file:

```sh
php bin/agent --init
```

By default, the config path is:

```text
~/.php-agent/config.json
```

You can inspect the active configuration:

```sh
php bin/agent --config-show
```

The default provider points at a local OpenAI-compatible endpoint:

```json
{
  "ai": {
    "default_provider": "xrea",
    "providers": {
      "xrea": {
        "url": "http://localhost:18080/v1/chat/completions",
        "api_key": "",
        "model": "default"
      }
    }
  },
  "worker": {
    "auth_token": "",
    "legacy_exec_enabled": false,
    "capabilities": {
      "process": {
        "runSafe": {
          "enabled": false
        }
      }
    }
  }
}
```

See `php-agent.example.json` for an example that also includes an OpenAI-style remote provider.

To use a custom config directory:

```sh
php bin/agent --config /path/to/config-dir --config-show
```

The file name inside that directory is always `config.json`.

### Worker configuration keys

| Key | Default | Description |
|-----|---------|-------------|
| `worker.auth_token` | `""` | Bearer token for API authentication (empty = no auth) |
| `worker.cors_allowed_origins` | `[]` | Allowed CORS origins |
| `worker.legacy_exec_enabled` | `false` | Enable legacy `exec` action (disabled by default) |
| `worker.workspace` | current directory | Workspace root for filesystem capabilities |
| `worker.capabilities.process.runSafe.enabled` | `false` | Enable the process sandbox capability |
| `worker.capabilities.process.runSafe.allowed_binaries` | `["php","composer","git"]` | Allowed binaries for `process.runSafe` |

## CLI Usage

Show help:

```sh
php bin/agent --help
```

Start the interactive REPL:

```sh
php bin/agent
```

Send one prompt:

```sh
php bin/agent -p "Hello"
```

Run an allow-listed shell command:

```sh
php bin/agent --exec "pwd"
```

The command response is JSON:

```json
{
  "status": "success",
  "data": "/path/to/project\n",
  "error": null,
  "exit_code": 0,
  "duration": 0.0012
}
```

## HTTP API

Start the built-in PHP server:

```sh
composer serve
```

With Docker:

```sh
docker compose up app
```

This serves `public/index.php` at:

```text
http://localhost:8080
```

The API accepts `POST` requests with JSON bodies.

### Supported actions

| Action | Description |
|--------|-------------|
| `ping` | Health check; returns `{"status":"success","data":"pong"}` |
| `info` | Returns PHP/environment info |
| `exec` | Legacy command runner (disabled by default) |
| `task.execute` | Capability-based execution via `CapabilityRouter` |

### Ping

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"ping"}'
```

### Info

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"info"}'
```

### Legacy exec (disabled by default)

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"exec","command":"pwd"}'
```

The legacy `exec` action is **disabled by default**. Set `worker.legacy_exec_enabled` to `true` in config to re-enable it.

### Capability-based execution (`task.execute`)

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"task.execute","capability":"system.info","input":{}}'
```

#### Available capabilities

| Capability name | Description |
|-----------------|-------------|
| `system.info` | Returns PHP version, OS info, loaded extensions, etc. |
| `fs.list` | Lists directory contents (sandboxed to workspace) |
| `fs.read` | Reads a file from the sandboxed workspace |
| `fs.write` | Writes content to a file in the sandboxed workspace |
| `process.runSafe` | Runs an allow-listed binary via argv array (no shell) |

#### Process runSafe capability

This capability executes commands using `proc_open` with an argv array (bypasses the shell). It is **disabled by default**. Enable it via config:

```json
{
  "worker": {
    "capabilities": {
      "process": {
        "runSafe": {
          "enabled": true,
          "allowed_binaries": ["php", "composer", "git"]
        }
      }
    }
  }
}
```

Example:

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"task.execute","capability":"process.runSafe","input":{"argv":["php","-v"]}}'
```

## Command Execution Policy

### Legacy `exec` action

The legacy `CommandRunner::run()` method checks only the first command token against a static allow-list and runs commands through `/bin/sh`. It is disabled by default.

### Capability-based execution

New capabilities use a capability router pattern. Each capability declares its name via `CapabilityInterface::name()` and handles input validation internally. The `process.runSafe` capability uses `CommandRunner::runArgv()` which passes commands as argv arrays directly to `proc_open`, bypassing the shell entirely.

## Build PHAR

Build the archive:

```sh
composer build
```

This creates:

```text
php-agent.phar
```

Run it:

```sh
php php-agent.phar --help
```

PHAR packaging preserves the full directory structure (including `src/` paths) so Composer's PSR-4 autoload resolves correctly inside the archive. The PHAR can be used as a drop-in executable without any external dependencies.

## Development Notes

Run syntax checks when PHP is available:

```sh
find . -path './vendor' -prune -o -name '*.php' -print -exec php -l {} \;
```

Validate Composer metadata:

```sh
composer validate --no-check-publish
```

Run tests:

```sh
composer test
```

Run tests in Docker:

```sh
docker compose run --rm test
```

## Repository Layout

```text
bin/agent                  CLI entry point
public/index.php           HTTP JSON API entry point
src/Agent.php              Application facade
src/ApiHandler.php         API action dispatcher (with legacy exec)
src/CommandRunner.php      Allow-listed command execution + runArgv()
src/TaskResult.php         Structured result object
src/AI/Client.php          OpenAI-compatible chat client
src/Capability/
  CapabilityInterface.php   Capability contract
  CapabilityRouter.php       Capability executor
  SystemInfoCapability.php  system.info capability
  Process/
    RunSafeCapability.php   process.runSafe capability
src/Filesystem/
  PathSandbox.php           Workspace path validator
  ListCapability.php        fs.list capability
  ReadCapability.php        fs.read capability
  WriteCapability.php       fs.write capability
src/Config/ConfigManager.php
src/Console/Repl.php
src/Task/
  TaskRecord.php            Immutable task record DTO
  TaskStoreInterface.php    Contract for task stores
  FileTaskStore.php         JSON-backed file store
build.php                  PHAR builder
stub.php                   PHAR entry stub
php-agent.example.json     Example configuration
Dockerfile                 PHP development/runtime image
compose.yml                Local serve/test services
phpunit.xml.dist           Pest/PHPUnit configuration
tests/                     Pest tests
```

## Current Limitations

- CORS currently allows all origins when configured.
- Legacy `exec` action is disabled by default; re-enable via config.
- Command safety for the legacy `exec` path is coarse (first-token check only).
- The `process.runSafe` capability must be explicitly enabled in config.
- `vendor/autoload.php` must exist before running CLI or HTTP entry points.
- Test coverage is still minimal and focused on current behavior.
