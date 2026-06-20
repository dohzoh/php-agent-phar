# 06 PHAR And Docs

## Context

Files to modify or create:

- Modify `build.php`
- Modify `stub.php` if needed
- Modify `README.md`
- Modify `AGENT.md`
- Update or add tests if build logic is extracted

Related existing code:

- `build.php` builds `src` into the archive without preserving the `src/` prefix.
- `stub.php` requires `phar://php-agent.phar/vendor/autoload.php`.
- `composer.json` maps `Xrea\\Agent\\` to `src/`.
- `docs/reconsidered_plan.md` flags PHAR packaging as unverified.

## Input / Output

Expected build command:

```sh
composer build
```

Expected artifact:

```text
php-agent.phar
```

Expected smoke commands:

```sh
php php-agent.phar --help
php php-agent.phar --config-show --config /tmp/php-agent-config
```

## Implementation Steps

1. Inspect generated PHAR layout.
2. Fix `build.php` so Composer autoload paths work inside the PHAR.
   - Preserve `src/` paths when adding source files.
   - Preserve `vendor/` paths when adding vendor files.
   - Include `composer.json` only if useful; not required for runtime.
3. Confirm `stub.php` path to `vendor/autoload.php` is valid.
4. Ensure build fails clearly when `vendor/autoload.php` is missing:

```text
Error: run composer install before building.
```

5. Ensure `php-agent.phar` remains ignored by Git.
6. Update `README.md`:
   - capability API examples,
   - authentication setup,
   - Docker test commands,
   - PHAR verification commands.
7. Update `AGENT.md`:
   - current architecture,
   - test requirements,
   - safety constraints.
8. Update `docs/reconsidered_plan.md` if implementation differs from the plan.

## Constraints

- Do not commit `php-agent.phar`.
- Do not commit `vendor/`.
- Do not make the PHAR build depend on Docker only.
- Keep CLI help output accurate.

## Tests

Run:

```sh
docker compose run --rm test
docker compose run --rm test sh -lc "git config --global --add safe.directory /app && composer validate --no-check-publish"
docker compose run --rm test sh -lc "find . -path ./vendor -prune -o -name '*.php' -print -exec php -l {} \\;"
docker compose run --rm test sh -lc "composer build && php php-agent.phar --help"
```

If `phar.readonly` blocks build inside the container, update Docker or command invocation to run PHP with:

```sh
php -d phar.readonly=0 build.php
```

Then update `composer.json` build script accordingly if needed.
