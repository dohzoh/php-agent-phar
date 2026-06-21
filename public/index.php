<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Xrea\Agent\ApiHandler;
use Xrea\Agent\Capability\CapabilityRouter;
use Xrea\Agent\CommandRunner;
use Xrea\Agent\Config\ConfigManager;
use Xrea\Agent\Http\Auth;

header('Content-Type: application/json; charset=utf-8');

$config = new ConfigManager();

// CORS handling: only emit allowed-origin header when configured and matched
$allowedOrigins = (array) $config->get('worker.cors_allowed_origins', []);
if ($allowedOrigins !== []) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if (in_array($origin, $allowedOrigins, true)) {
        header("Access-Control-Allow-Origin: $origin");
    }
}
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Authentication check
$auth = new Auth($config->get('worker.auth_token'));
if (!$auth->authorize($_SERVER)) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'error' => 'Unauthorized',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'error' => 'Method not allowed. Use POST.',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => 'Invalid JSON: ' . json_last_error_msg(),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $runner = new CommandRunner();
    $router = null;

    // Wire task store for task.status action (always needed)
    require_once __DIR__ . '/../src/Task/TaskStoreInterface.php';
    require_once __DIR__ . '/../src/Task/FileTaskStore.php';
    require_once __DIR__ . '/../src/Task/TaskRecord.php';
    $taskStoreDir = rtrim($config->get('worker.task_store_dir', sys_get_temp_dir() . '/php-agent-tasks'), DIRECTORY_SEPARATOR);
    if ($taskStoreDir === '') {
        $taskStoreDir = sys_get_temp_dir() . '/php-agent-tasks';
    }
    $taskStore = new \Xrea\Agent\Task\FileTaskStore($taskStoreDir);

    // Only wire capability router when task.execute is needed (backward compat: keep null for legacy actions)
    if (isset($body['action']) && $body['action'] === 'task.execute') {
        require_once __DIR__ . '/../src/Capability/SystemInfoCapability.php';
        require_once __DIR__ . '/../src/Filesystem/PathSandbox.php';
        require_once __DIR__ . '/../src/Capability/Filesystem/ListCapability.php';
        require_once __DIR__ . '/../src/Capability/Filesystem/ReadCapability.php';
        require_once __DIR__ . '/../src/Capability/Filesystem/WriteCapability.php';

        $workspace = rtrim($config->get('worker.workspace', getcwd() ?: '.'), DIRECTORY_SEPARATOR);
        if ($workspace === '') {
            $workspace = '.';
        }
        $sandbox = new \Xrea\Agent\Filesystem\PathSandbox($workspace);

        // Build capabilities list from config
        $capDefs = [
            new \Xrea\Agent\Capability\SystemInfoCapability(),
            new \Xrea\Agent\Capability\Filesystem\ListCapability($sandbox),
            new \Xrea\Agent\Capability\Filesystem\ReadCapability($sandbox),
            new \Xrea\Agent\Capability\Filesystem\WriteCapability($sandbox),
        ];

        // Wire process.runSafe if enabled by config
        $runSafeEnabled = (bool) $config->get('worker.capabilities.process.runSafe.enabled', false);
        if ($runSafeEnabled) {
            require_once __DIR__ . '/../src/Capability/Process/RunSafeCapability.php';
            $allowedBinaries = (array) $config->get('worker.capabilities.process.runSafe.allowed_binaries', ['php', 'composer', 'git']);
            $runSafeCwd = $config->get('worker.capabilities.process.runSafe.cwd');

            $capDefs[] = new \Xrea\Agent\Capability\Process\RunSafeCapability(
                $runner,
                enabled: true,
                allowedBinaries: $allowedBinaries,
                cwd: is_string($runSafeCwd) && $runSafeCwd !== '' ? $runSafeCwd : null,
            );
        }

        $router = new CapabilityRouter($capDefs);
    }

    // Determine legacy exec enablement from config
    $legacyExecEnabled = (bool) $config->get('worker.legacy_exec_enabled', false);
    $handler = new ApiHandler($runner, $router ?? null, $legacyExecEnabled, $taskStore);
    $result = $handler->handle($body ?? []);

    if ($result->status === 'error') {
        http_response_code(400);
    } else {
        http_response_code(200);
    }

    echo $result->toJson();
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => 'Internal error: ' . $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
