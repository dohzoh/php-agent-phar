<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Xrea\Agent\ApiHandler;
use Xrea\Agent\CommandRunner;

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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
    $handler = new ApiHandler(new CommandRunner());
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
