#!/usr/bin/env php
<?php

declare(strict_types=1);

if (extension_loaded('phar')) {
    Phar::mapPhar('php-agent.phar');
}

require_once 'phar://php-agent.phar/vendor/autoload.php';

use Xrea\Agent\Agent;

$shortOpts = 'p:c::';
$longOpts = ['prompt:', 'config::', 'init', 'config-show', 'exec:', 'help'];
$options = getopt($shortOpts, $longOpts);

if (isset($options['help']) || (empty($options) && $argc > 1 && in_array($argv[1] ?? '', ['-h', '--help', 'help'], true))) {
    echo <<<HELP
PHP Agent - AI-powered assistant for XREA

Usage:
  php php-agent.phar              Interactive REPL mode
  php php-agent.phar -p <text>    Process a single prompt (non-interactive)
  php php-agent.phar --init       Initialize config file
  php php-agent.phar --config-show  Show current configuration
  php php-agent.phar --exec <cmd> Execute a shell command
  php php-agent.phar --help       Show this help

Options:
  -p, --prompt <text>     Send a prompt to the AI and print the response
  -c, --config <dir>      Use custom config directory
  --init                  Create default config file
  --config-show           Display current configuration
  --exec <command>        Execute a shell command
  -h, --help              Show this help

Examples:
  php php-agent.phar
  php php-agent.phar -p "Hello, what can you do?"
  php php-agent.phar --init
  php php-agent.phar --exec "ls -la"
  ssh xrea "php php-agent.phar -p 'List files in /home'"

HELP;
    exit(0);
}

$configDir = null;
if (isset($options['c'])) {
    $configDir = is_array($options['c']) ? end($options['c']) : $options['c'];
}
if (isset($options['config'])) {
    $val = $options['config'];
    if ($val === false) {
        // getopt with :: doesn't pick up space-separated values for long opts
        // look at raw argv for the next argument after --config
        foreach ($argv as $i => $arg) {
            if ($arg === '--config' && isset($argv[$i + 1])) {
                $val = $argv[$i + 1];
                break;
            }
        }
    }
    $configDir = is_array($val) ? end($val) : $val;
}

$agent = new Agent($configDir);

if (isset($options['init'])) {
    $agent->initConfig();
    exit(0);
}

if (isset($options['config-show'])) {
    $agent->showConfig();
    exit(0);
}

if (isset($options['exec'])) {
    $cmd = is_array($options['exec']) ? end($options['exec']) : $options['exec'];
    $result = $agent->runCommand($cmd);
    echo $result->toJson() . "\n";
    exit($result->exitCode ?? 0);
}

if (isset($options['p']) || isset($options['prompt'])) {
    $prompt = isset($options['p'])
        ? (is_array($options['p']) ? end($options['p']) : $options['p'])
        : (is_array($options['prompt']) ? end($options['prompt']) : $options['prompt']);
    echo $agent->runPrompt($prompt) . "\n";
    exit(0);
}

$agent->runRepl();

__HALT_COMPILER();
