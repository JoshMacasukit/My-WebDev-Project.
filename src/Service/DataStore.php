<?php

namespace App\Service;

class DataStore
{
    private string $file;

    public function __construct()
    {
        $this->file = dirname(__DIR__, 2) . '/var/data/FreshBasket Data.txt';
        $this->initialize();
    }

    private function initialize(): void
    {
        if (!file_exists($this->file)) {
            if (!is_dir(dirname($this->file))) {
                mkdir(dirname($this->file), 0777, true);
            }

            $default = $this->defaultData();

            // Create default admin securely
            $default['admin'] = [
                'email' => 'admin@freshbasket.ph',
                'password' => password_hash('admin123', PASSWORD_DEFAULT)
            ];

            $this->write($default);
        }
    }

    private function defaultData(): array
    {
        return [
            'admin' => [],
            'users' => [],
            'products' => [],
            'orders' => [],
            'logs' => []
        ];
    }

    public function read(): array
    {
        if (!file_exists($this->file)) {
            return $this->defaultData();
        }

        $data = json_decode(file_get_contents($this->file), true);

        if (!is_array($data)) {
            return $this->defaultData();
        }

        return array_replace_recursive($this->defaultData(), $data);
    }

    public function write(array $data): void
    {
        $fp = fopen($this->file, 'c+');
        if (!$fp) return;

        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    public function id(string $prefix = ''): string
    {
        return $prefix . strtoupper(uniqid());
    }

    public function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    public function log(string $message): void
    {
        $data = $this->read();
        $data['logs'][] = [
            'message' => $message,
            'time' => $this->now()
        ];
        $this->write($data);
    }
}
