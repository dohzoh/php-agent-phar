<?php

namespace Xrea\Agent\Config;

class ConfigManager
{
    private const CONFIG_DIR = '.php-agent';
    private const CONFIG_FILE = 'config.json';

    private array $data;
    private string $configPath;

    public function __construct(?string $configDir = null)
    {
        $configDir ??= $this->getDefaultConfigDir();
        $this->configPath = $configDir . '/' . self::CONFIG_FILE;
        $this->data = $this->load();
    }

    public static function getDefaultConfigDir(): string
    {
        $home = getenv('HOME') ?: (getenv('USERPROFILE') ?: ('/home/' . getenv('USER')));
        return $home . '/' . self::CONFIG_DIR;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $value = $this->data;
        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }
        return $value;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $ref = &$this->data;
        foreach ($keys as $k) {
            if (!isset($ref[$k]) || !is_array($ref[$k])) {
                $ref[$k] = [];
            }
            $ref = &$ref[$k];
        }
        $ref = $value;
    }

    public function load(): array
    {
        if (!file_exists($this->configPath)) {
            return $this->getDefaults();
        }
        $content = file_get_contents($this->configPath);
        if ($content === false) {
            return $this->getDefaults();
        }
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->getDefaults();
        }
        return array_replace_recursive($this->getDefaults(), $decoded);
    }

    public function save(): bool
    {
        $dir = dirname($this->configPath);
        if (!is_dir($dir) && !mkdir($dir, 0700, true)) {
            return false;
        }
        $json = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return file_put_contents($this->configPath, $json) !== false;
    }

    public function getDefaults(): array
    {
        return [
            'ai' => [
                'default_provider' => 'xrea',
                'providers' => [
                    'xrea' => [
                        'url' => 'http://localhost:18080/v1/chat/completions',
                        'api_key' => '',
                        'model' => 'default',
                    ],
                ],
            ],
        ];
    }

    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    public function getAll(): array
    {
        return $this->data;
    }
}
