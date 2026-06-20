# PHP Agent Reconsidered Plan

## Purpose

This document revisits the original feasibility plan in `docs/feasibility/php_agent_design.md` against the current implementation.

The original direction remains valid: XREA is a short-lived PHP hosting environment, so the agent should behave as a task-based worker controlled by a stronger parent orchestrator. The current code is a useful proof of concept, but it should not yet be treated as a secure worker.

## Current Implementation Summary

The repository currently provides:

- a CLI facade in `bin/agent`,
- a web endpoint in `public/index.php`,
- a simple action dispatcher in `ApiHandler`,
- command execution through `CommandRunner`,
- OpenAI-compatible chat completions through `AI\Client`,
- JSON config loading through `ConfigManager`,
- PHAR build scripts.

This maps to the original "Parent-Worker" model, but only at the minimum synchronous request/response level.

## Problems Found

### 1. Command Safety Is Too Broad

`CommandRunner::isSafeCommand()` checks only the first command token. This means argument-level risk is not controlled. The allow-list also contains high-impact commands such as `rm`, `mv`, `chmod`, `curl`, `wget`, `python`, `node`, `composer`, `npm`, and `pip`.

Impact:

- A remote caller can run broad filesystem or network operations if `exec` is exposed.
- Shell metacharacters and chained commands are not structurally parsed.
- The safety policy is not specific to XREA's restricted shell constraints.

### 2. HTTP API Has No Trust Boundary

`public/index.php` accepts JSON POST requests, enables permissive CORS, and has no authentication.

Impact:

- It is unsuitable for public deployment.
- Any exposed endpoint can become a remote command execution interface.
- There is no parent-orchestrator identity check.

### 3. Task Model Is Too Low-Level

The original plan described well-defined tasks such as file operations, database queries, and command execution. The current API exposes `exec` directly.

Impact:

- The parent agent must understand raw shell details.
- The worker cannot apply task-specific validation.
- Future async status tracking is harder to add cleanly.

### 4. Runtime Limits Are Not Fully Reflected

The original plan assumed XREA-like limits: short execution windows, CPU constraints, and restricted shell behavior. The current runner has a 25 second timeout, but there is no queue, job status, retry policy, or native PHP fallback for restricted shell cases.

Impact:

- Longer operations cannot be split or resumed.
- Failures under `rbash` may be difficult to diagnose.
- Shell execution is overused where native PHP operations would be safer.

### 5. Packaging Is Unverified

PHAR generation exists, but the archive layout should be verified against Composer's PSR-4 autoload behavior.

Impact:

- `php-agent.phar` may not resolve classes correctly after packaging.
- Build success does not necessarily mean runtime success.

### 6. Development Baseline Was Missing

The project lacked Docker, a compose file, and tests.

Impact:

- Contributors without local PHP cannot verify changes.
- Safety-sensitive behavior has no regression coverage.
- CI integration would be premature without a repeatable local test command.

## Reconstructed Plan

### Phase 1: Stabilize Development Infrastructure

Goal: make local and container-based verification repeatable.

Actions:

- Add `Dockerfile` for a PHP CLI development image.
- Add `compose.yml` for serving and testing in containers.
- Add Pest as the test runner.
- Add smoke/unit tests for config, API dispatch, task results, and command policy.
- Keep the implementation dependency-light until the worker boundary is clearer.

### Phase 2: Harden the Synchronous Worker

Goal: make the current request/response worker safe enough for controlled environments.

Actions:

- Add API authentication, preferably a shared bearer token configured outside the document root.
- Disable wildcard CORS by default.
- Replace first-token command checks with a structured command policy.
- Split command execution into named capabilities, for example:
  - `fs.list`
  - `fs.read`
  - `fs.write`
  - `process.runSafe`
  - `system.info`
- Validate arguments per capability instead of validating an entire shell string.
- Narrow the default command allow-list.

### Phase 3: Prefer Native PHP Capabilities

Goal: reduce dependence on XREA shell behavior.

Actions:

- Implement native PHP handlers for common filesystem operations.
- Add size limits for reads and writes.
- Add path sandboxing rooted at a configured workspace.
- Add database capability only behind explicit configuration.
- Keep raw shell execution as an optional, disabled-by-default capability.

### Phase 4: Add Task State

Goal: support XREA's short execution model without long-running daemons.

Actions:

- Add a task store backed by files or a database.
- Add task IDs and status documents.
- Support `POST /task/execute` for synchronous tasks.
- Support `POST /task/enqueue` and `GET /task/{id}` for deferred tasks if needed.
- Keep orchestration and planning in the parent agent.

### Phase 5: Verify Packaging and Deployment

Goal: make the worker deployable to constrained PHP hosting.

Actions:

- Verify PHAR autoload behavior inside Docker.
- Document FTP/SFTP deployment expectations.
- Document required PHP extensions and disabled-function compatibility.
- Add a deploy checklist for XREA.

## Target Architecture

The preferred architecture is:

```text
Parent Orchestrator
  |
  | HTTPS + bearer token
  v
PHP Worker API
  |
  +-- Capability router
  |     +-- Native filesystem operations
  |     +-- System info
  |     +-- Optional safe process runner
  |
  +-- Task store
        +-- File or database backed status records
```

The worker should remain small. It should execute bounded tasks and return structured results. It should not perform long-term planning, heavy computation, or broad shell access.

## Immediate Next Steps

1. Run the new Pest tests in Docker.
2. Add authentication to `public/index.php`.
3. Introduce a capability-oriented request schema.
4. Replace the broad shell allow-list with narrowly scoped handlers.
5. Verify and fix PHAR packaging.
