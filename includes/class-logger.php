<?php
/**
 * Logger — Escritura y lectura de logs del plugin.
 *
 * @package SecopSuite
 */

declare(strict_types=1);

namespace SecopSuite;

if (!defined('ABSPATH')) {
    exit;
}

final class Logger
{
    private const LOG_DIR  = 'logs';
    private const LOG_FILE = 'import.log';

    public static function log(string $message): void
    {
        $dir = SECOP_SUITE_DIR . self::LOG_DIR;

        if (!file_exists($dir)) {
            wp_mkdir_p($dir);
            // Proteger el directorio
            file_put_contents($dir . '/.htaccess', "Order deny,allow\nDeny from all", LOCK_EX);
            file_put_contents($dir . '/index.php', '<?php // Silence is golden.', LOCK_EX);
        }

        $timestamp = wp_date('Y-m-d H:i:s');
        $entry     = "[{$timestamp}] {$message}\n";

        file_put_contents($dir . '/' . self::LOG_FILE, $entry, FILE_APPEND | LOCK_EX);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[SECOP Suite] {$message}");
        }
    }

    public static function read(): string
    {
        $file = SECOP_SUITE_DIR . self::LOG_DIR . '/' . self::LOG_FILE;
        return file_exists($file) ? (string) file_get_contents($file) : '';
    }

    public static function clear(): void
    {
        $file = SECOP_SUITE_DIR . self::LOG_DIR . '/' . self::LOG_FILE;
        if (file_exists($file)) {
            file_put_contents($file, '', LOCK_EX);
        }
    }
}
