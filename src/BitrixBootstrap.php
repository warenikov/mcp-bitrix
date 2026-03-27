<?php

namespace Warenikov\McpBitrix;

class BitrixBootstrap
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        $root = getenv('BITRIX_DOCUMENT_ROOT') ?: '/var/www/html';

        $_SERVER['DOCUMENT_ROOT']   = $root;
        $_SERVER['HTTP_HOST']       = 'localhost';
        $_SERVER['SERVER_NAME']     = 'localhost';
        $_SERVER['SERVER_PORT']     = '80';
        $_SERVER['REQUEST_URI']     = '/';
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['HTTPS']           = '';

        // Отключаем статистику и проверку прав — сервер работает как cron
        define('NO_KEEP_STATISTIC',   true);
        define('NOT_CHECK_PERMISSIONS', true);
        define('BX_CRONTAB',          true);
        define('BX_CRONTAB_SUPPORT',  true);

        // Подавляем вывод во время инициализации Битрикса
        ob_start();
        try {
            require $root . '/bitrix/modules/main/include/prolog_before.php';
        } finally {
            ob_end_clean();
        }

        self::$initialized = true;
    }
}
