# AGENT.md

This repository contains a small PHP-based AI agent intended for use in an XREA-style hosting environment. Keep changes conservative: the project is currently a compact prototype with no test suite and no installed runtime in this workspace.

## Project Shape

- `bin/agent` is the CLI entry point.
- `public/index.php` is the HTTP JSON API entry point.
- `src/Agent.php` is a thin facade over config, REPL, and command execution.
- `src/Console/Repl.php` handles interactive and single-prompt AI usage.
- `src/AI/Client.php` calls an OpenAI-compatible chat completions endpoint via cURL.
- `src/CommandRunner.php` executes allow-listed shell commands with a short timeout.
- `src/Config/ConfigManager.php` loads and saves `~/.php-agent/config.json` by default.
- `build.php` and `stub.php` are for PHAR packaging.

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
```

When PHP is available, run syntax checks before finishing PHP edits:

```sh
find . -path './vendor' -prune -o -name '*.php' -print -exec php -l {} \;
```

If Composer is available, also run:

```sh
composer validate --no-check-publish
```

## Safety Notes

`CommandRunner::isSafeCommand()` currently checks only the first command token. Treat the command API as unsafe for public or untrusted exposure until command parsing, argument validation, authentication, and command policy are hardened.

The current allow-list includes commands with broad effects such as `rm`, `mv`, `chmod`, `curl`, `wget`, `python`, `node`, `composer`, `npm`, and `pip`. Be especially careful when changing API behavior around `exec`.

`public/index.php` currently allows cross-origin POST requests from any origin and has no authentication. Do not present it as production-ready without adding access control.

## Known Gaps

- No automated tests are present.
- `README.md` was initially empty.
- `vendor/autoload.php` is required by the CLI and web entry points but is not tracked.
- PHAR packaging should be verified after Composer install; the current build layout may need adjustment so PSR-4 autoload paths work inside the archive.
- AI configuration supports OpenAI-compatible chat completions, but only the URL, API key, and model are currently configurable.

## Editing Guidance

- Prefer small, direct changes aligned with the existing simple class structure.
- Avoid adding frameworks unless the user explicitly asks for a larger redesign.
- Keep generated or local files such as `vendor/`, `build/`, and `*.phar` out of Git.
- If changing command execution, update both CLI behavior and HTTP API expectations.
- If adding config keys, update `ConfigManager::getDefaults()`, `php-agent.example.json`, and `README.md` together.
