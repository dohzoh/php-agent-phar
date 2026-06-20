# Feasibility Study: PHP-based Sub-agent for XREA

## 1. Objective
To evaluate the feasibility and design an architecture for a PHP-based sub-agent (similar to `opencode`) running on the XREA hosting environment. This sub-agent would act as a specialized executor for tasks delegated by a higher-level agent (e.g., a Multica Agent or a Node.js-based Orchestrator).

## 2. Target Environment Analysis (XREA)
Based on previous research (DOZ-2), the XREA environment presents the following characteristics:
- **Runtime:** Apache 2.4, PHP 7.4 - 8.5 (available).
- **Shell:** `rbash` (restricted Bash). Output redirection might be restricted.
- **Resource Limits:**
    - **CPU:** ~15% usage cap (forced termination if exceeded).
    - **Execution Time:** ~30 seconds per request/script.
- **Connectivity:** SSH (with IP restriction), REST API (`api.xrea.com`), MySQL/MariaDB, PostgreSQL.
- **Deployment:** Files via FTP/SFTP, management via Control Panel.

## 3. Proposed Architecture: "Parent-Worker" Model

Since XREA is optimized for short-lived web requests rather than long-running daemon processes, a **Task-Based Worker Model** is recommended.

### 3.1 Components
1.  **Orchestrator (Parent Agent):**
    - Running in a more robust environment (e.g., local, cloud, or a more powerful VPS).
    - Manages state, long-term planning, and high-level reasoning.
    - Communicates with the XREA worker via HTTPS (REST API).

2.  **Sub-Agent (PHP Worker on XREA):**
    - A lightweight PHP application (e.g., Slim or a simple custom router) hosted on XREA.
    - **API Endpoints:**
        - `POST /task/execute`: Receives a specific, well-defined task (e.g., "Write to file X", "Query DB Y", "Run shell command Z").
        - `GET /status/{task_id}`: Checks the status of a long-running task (if implemented via a database/file).
    - **Execution Logic:** Uses `shell_exec()` or `proc_open()` to run CLI commands (subject to `rbash` limits) or performs native PHP operations (File IO, DB, API calls).

### 3.2 Communication Flow
1.  **Request:** Parent Agent sends a JSON-encoded task to the PHP Worker.
2.  **Processing:** The Worker validates the task, executes it, and handles errors.
3.  **Response:** The Worker returns a JSON response containing the result, status, or error details.

## 4. Addressing Constraints

| Constraint | Mitigation Strategy |
| :--- | :--- |
| **30s Execution Limit** | **Task Atomicity:** Ensure each sub-task is highly granular. For long workflows, the Parent Agent must manage the sequence of multiple sub-tasks. |
| **CPU Cap (15%)** | **Offloading:** Perform heavy computation (LLM inference, heavy data processing) on the Parent Agent or an external service. The PHP worker should only perform "glue" logic and lightweight execution. |
| **`rbash` (Restricted Shell)** | **Whitelisting:** Focus on executing a pre-defined set of safe commands or using native PHP functions for filesystem and database operations. |
| **No Long-running Processes** | **Asynchronous/Cron Model:** For tasks requiring more time, the Worker can write a "job" to a file/DB, and a Cron job can pick it up, or the Parent can poll a "status" endpoint. |

## 5. Implementation Implementation Strategy (PoC)

### 5.1 Tech Stack
- **Language:** PHP 8.x
- **Package Manager:** Composer
- **Dependencies:**
    - `guzzlehttp/guzzle` (for API interaction, if needed).
    - `symfony/console` (for CLI-based worker tasks).
    - `vlucas/phpdotenv` (for environment configuration).

### 5.2 Implementation Steps (Proposed)
1.  **Phase 1 (Infrastructure):** Set up a minimal PHP project with Composer and deploy it to XREA.
2.  **Phase 2 (Core API):** Implement a secure `POST /execute` endpoint that can run a set of "safe" shell commands.
3.  **Phase 3 (Task Integration):** Connect the Parent Agent to the Worker via a mock task.
4.  **Phase 4 (Expansion):** Add native PHP capabilities (e.g., direct DB interaction, file system management) to reduce reliance on shell commands.

## 6. Conclusion
Implementing a PHP-based sub-agent is **highly feasible** for the intended purpose of lightweight, API-driven execution on XREA. The key to success lies in **granularity** (keeping tasks under 30s) and **delegation** (keeping heavy logic in the Parent).
