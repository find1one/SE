<?php
/**
 * 日志记录类
 */
class Logger {
    private bool $enabled;
    private string $logPath;
    private string $level;
    private array $levels = ['debug' => 0, 'info' => 1, 'warning' => 2, 'error' => 3];

    public function __construct(array $config) {
        $this->enabled = $config['enabled'] ?? true;
        $this->logPath = $config['path'] ?? __DIR__ . '/../../logs/';
        $this->level = $config['level'] ?? 'info';

        // 确保日志目录存在
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0755, true);
        }
    }

    public function debug(string $message, array $context = []): void {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void {
        if (!$this->enabled) {
            return;
        }

        if ($this->levels[$level] < $this->levels[$this->level]) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[{$timestamp}] [{$level}] {$message}{$contextStr}\n";

        $logFile = $this->logPath . 'payment_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
