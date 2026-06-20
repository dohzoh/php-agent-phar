<?php

declare(strict_types=1);

use Xrea\Agent\Config\ConfigManager;

it('loads defaults when no config file exists', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));

    $config = new ConfigManager($dir);

    expect($config->get('ai.default_provider'))->toBe('xrea')
        ->and($config->get('ai.providers.xrea.url'))->toBe('http://localhost:18080/v1/chat/completions');
});

it('sets and gets nested config values', function (): void {
    $dir = sys_get_temp_dir() . '/php-agent-test-' . bin2hex(random_bytes(4));
    $config = new ConfigManager($dir);

    $config->set('ai.default_provider', 'openai');

    expect($config->get('ai.default_provider'))->toBe('openai')
        ->and($config->get('missing.value', 'fallback'))->toBe('fallback');
});
