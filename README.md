# PHP Agent

PHP Agent is a small PHP-based AI assistant and command runner designed for constrained hosting environments such as XREA. It provides:

- a CLI REPL and single-prompt mode,
- a minimal HTTP JSON API,
- an OpenAI-compatible chat completions client,
- allow-listed shell command execution,
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
  }
}
```

See `php-agent.example.json` for an example that also includes an OpenAI-style remote provider.

To use a custom config directory:

```sh
php bin/agent --config /path/to/config-dir --config-show
```

The file name inside that directory is always `config.json`.

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

This serves `public/index.php` at:

```text
http://localhost:8080
```

The API accepts `POST` requests with JSON bodies.

Ping:

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"ping"}'
```

Info:

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"info"}'
```

Execute a command:

```sh
curl -X POST http://localhost:8080 \
  -H 'Content-Type: application/json' \
  -d '{"action":"exec","command":"pwd"}'
```

Supported actions are:

- `ping`
- `info`
- `exec`

## Command Execution Policy

Commands are handled by `CommandRunner`. The current implementation:

- checks only the first command token against a static allow-list,
- applies a maximum execution time of 25 seconds,
- captures stdout, stderr, exit code, and duration,
- returns a structured `TaskResult`.

The allow-list currently includes common filesystem, language runtime, package manager, and network commands. This is convenient for controlled local use, but it is not sufficient security for public exposure.

Do not expose `exec` to untrusted users without adding authentication, stricter parsing, argument validation, and a narrower command policy.

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

PHAR packaging should be verified after dependency installation. The current packaging path layout may need adjustment if Composer's PSR-4 autoload map does not resolve classes correctly inside the archive.

## Development Notes

Run syntax checks when PHP is available:

```sh
find . -path './vendor' -prune -o -name '*.php' -print -exec php -l {} \;
```

Validate Composer metadata:

```sh
composer validate --no-check-publish
```

There is currently no automated test suite.

## Repository Layout

```text
bin/agent                  CLI entry point
public/index.php           HTTP JSON API entry point
src/Agent.php              Application facade
src/ApiHandler.php         API action dispatcher
src/CommandRunner.php      Allow-listed command execution
src/TaskResult.php         Structured result object
src/AI/Client.php          OpenAI-compatible chat client
src/Config/ConfigManager.php
src/Console/Repl.php
build.php                  PHAR builder
stub.php                   PHAR entry stub
php-agent.example.json     Example configuration
```

## Current Limitations

- No authentication on the HTTP API.
- CORS currently allows all origins.
- Command safety is coarse and based only on the first token.
- `vendor/autoload.php` must exist before running CLI or HTTP entry points.
- No tests are included yet.
