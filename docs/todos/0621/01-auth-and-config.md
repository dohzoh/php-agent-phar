# 01 Auth And Config

## Context

Files to modify or create:

- Modify `src/Config/ConfigManager.php`
- Modify `public/index.php`
- Modify `php-agent.example.json`
- Add `src/Http/Auth.php`
- Add or update tests under `tests/Unit`
- Update `README.md` and `AGENT.md` after implementation

Related existing code:

- `ConfigManager::getDefaults()` currently returns only AI provider settings.
- `public/index.php` currently allows all CORS origins and has no authentication.
- `ApiHandler` should remain focused on dispatching already-authorized request bodies.

## Input / Output

Create class:

```php
namespace Xrea\Agent\Http;

class Auth
{
    public function __construct(
        private readonly ?string $token,
    ) {
    }

    public function isRequired(): bool;

    /**
     * @param array<string, mixed> $server
     */
    public function authorize(array $server): bool;

    /**
     * @param array<string, mixed> $server
     */
    public static function bearerTokenFromServer(array $server): ?string;
}
```

Config defaults to add:

```php
'worker' => [
    'auth_token' => '',
    'cors_allowed_origins' => [],
    'workspace' => getcwd() ?: '.',
    'capabilities' => [
        'process.runSafe' => [
            'enabled' => false,
        ],
    ],
],
```

HTTP behavior:

- If `worker.auth_token` is empty, auth is disabled for local/dev compatibility.
- If `worker.auth_token` is non-empty, request must include:

```text
Authorization: Bearer <token>
```

- Unauthorized requests return HTTP `401` with JSON:

```json
{"status":"error","error":"Unauthorized"}
```

## Implementation Steps

1. Add `src/Http/Auth.php`.
2. Implement `Auth::isRequired()` as `trim((string) $token) !== ''`.
3. Implement `Auth::bearerTokenFromServer()`:
   - read `HTTP_AUTHORIZATION` first,
   - fallback to `REDIRECT_HTTP_AUTHORIZATION`,
   - accept only case-insensitive `Bearer ` prefix,
   - return token string or `null`.
4. Implement `Auth::authorize()`:
   - return `true` if auth is not required,
   - compare configured token and incoming bearer token with `hash_equals`.
5. Update `ConfigManager::getDefaults()` with `worker` defaults.
6. Update `php-agent.example.json` with `worker.auth_token`, `cors_allowed_origins`, `workspace`, and process capability flag.
7. Update `public/index.php`:
   - instantiate `ConfigManager`,
   - configure CORS using `worker.cors_allowed_origins`,
   - only emit `Access-Control-Allow-Origin` when request origin is explicitly allowed,
   - preserve OPTIONS handling,
   - run `Auth` before reading/dispatching POST body.
8. Avoid changing CLI behavior in this step.

## Constraints

- Do not add dependencies.
- Keep `ApiHandler` unaware of HTTP headers.
- Do not log or print auth tokens.
- `cors_allowed_origins` must default to an empty array, not wildcard.
- HTTP errors must remain JSON.

## Tests

Add tests:

- `AuthTest`
  - auth disabled when token is empty,
  - bearer token parses from `HTTP_AUTHORIZATION`,
  - bearer token parses case-insensitive `Bearer`,
  - wrong token fails,
  - correct token passes.
- `ConfigManagerTest`
  - default worker config exists,
  - `worker.capabilities.process.runSafe.enabled` defaults to `false`.

Manual integration test can be added later; do not require a live server for unit tests.
