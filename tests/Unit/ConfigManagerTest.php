<?php

declare(strict_types=1);

use Xrea\Agent\Config\ConfigManager;

it('loads defaults when no config file exists', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));

    $config = new ConfigManager($dir);

    expect($config->get('ai.default_provider'))->toBe('xrea')
        ->and($config->get('ai.providers.xrea.url'))->toBe('http://localhost:18080/v1/chat/completions');
});

it('has default worker config', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    $config = new ConfigManager($dir);

    expect($config->get('worker.auth_token'))->toBe('')
        ->and($config->get('worker.cors_allowed_origins'))->toBe([])
        ->and(is_string($config->get('worker.workspace')))->toBeTrue()
        ->and($config->get('worker.task_store.path'))->toBeNull();
});

it('sets and gets nested config values', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    $config = new ConfigManager($dir);

    $config->set('ai.default_provider', 'openai');

    expect($config->get('ai.default_provider'))->toBe('openai')
        ->and($config->get('missing.value', 'fallback'))->toBe('fallback');
});

it('defaults process.runSafe.enabled to false', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    $config = new ConfigManager($dir);

    expect($config->get('worker.capabilities.process.runSafe.enabled'))->toBeFalse();
});
