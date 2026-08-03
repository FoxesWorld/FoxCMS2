<?php

declare(strict_types=1);

final class AdminFailurePresenter
{
    /** @return array<string, mixed> */
    public static function payload(Throwable $error, string $action, string $requestId): array
    {
        $action = trim($action) !== '' ? trim($action) : 'unknown';
        $requestId = trim($requestId) !== '' ? trim($requestId) : 'unavailable';
        $detail = self::detail($error);
        $message = $error instanceof HttpException
            ? $detail
            : 'Не удалось выполнить административную операцию «' . $action . '». Причина: ' . $detail;

        if (!str_contains($message, $requestId)) {
            $message .= ' Код события: ' . $requestId . '.';
        }

        return [
            'message' => $message,
            'type' => 'error',
            'requestId' => $requestId,
            'error' => [
                'action' => $action,
                'exception' => $error::class,
                'detail' => $detail,
                'requestId' => $requestId,
            ],
        ];
    }

    public static function status(Throwable $error): int
    {
        if ($error instanceof HttpException) {
            return $error->status();
        }
        if ($error instanceof DatabaseException) {
            return 503;
        }
        if ($error instanceof InvalidArgumentException || $error instanceof JsonException) {
            return 400;
        }
        return 500;
    }

    private static function detail(Throwable $error): string
    {
        if ($error instanceof DatabaseException) {
            return self::databaseDetail($error->getMessage());
        }
        if ($error instanceof JsonException) {
            return 'Сервер получил или сформировал некорректный JSON: ' . self::sanitize($error->getMessage());
        }
        if ($error instanceof HttpException) {
            return self::sanitize($error->getMessage());
        }
        if ($error instanceof InvalidArgumentException) {
            return 'Переданы некорректные данные: ' . self::sanitize($error->getMessage());
        }
        if ($error instanceof RuntimeException) {
            return self::sanitize($error->getMessage());
        }
        return 'Непредвиденная ошибка ' . $error::class . ': ' . self::sanitize($error->getMessage());
    }

    private static function databaseDetail(string $message): string
    {
        $safe = self::sanitize($message);
        if (preg_match("/Unknown column '([^']+)'/i", $message, $match) === 1) {
            return 'В базе данных отсутствует столбец «' . self::sanitize($match[1])
                . '». Код приложения и схема БД не синхронизированы. Выполните `php scripts/migrate.php`.';
        }
        if (preg_match("/Table '[^']*\.([^']+)' doesn't exist/i", $message, $match) === 1) {
            return 'В базе данных отсутствует таблица «' . self::sanitize($match[1])
                . '». Выполните `php scripts/migrate.php`.';
        }
        if (preg_match('/SQLSTATE\[(42S22|42S02)\]/i', $message) === 1) {
            return 'Структура базы данных устарела или неполна. Выполните `php scripts/migrate.php`. Техническая причина: ' . $safe;
        }
        if (preg_match('/SQLSTATE\[(08[0-9A-Z]{3})\]|Connection refused|server has gone away/i', $message) === 1) {
            return 'Соединение с базой данных недоступно. Проверьте состояние MySQL, адрес, порт и сетевое подключение.';
        }
        if (stripos($message, 'could not find driver') !== false) {
            return 'В PHP отсутствует PDO-драйвер MySQL (`pdo_mysql`). Установите или включите расширение и перезапустите PHP.';
        }
        if (preg_match('/Access denied|SQLSTATE\[28000\]/i', $message) === 1) {
            return 'База данных отклонила авторизацию. Проверьте пользователя БД и его права доступа.';
        }
        if (preg_match('/Duplicate entry|SQLSTATE\[23000\]/i', $message) === 1) {
            return 'Операция нарушает ограничение уникальности базы данных. Техническая причина: ' . $safe;
        }
        return 'Ошибка базы данных: ' . $safe;
    }

    private static function sanitize(string $message): string
    {
        $message = trim(str_replace(["\r", "\n", "\0"], ' ', $message));
        foreach (['ROOT_DIR', 'ENGINE_DIR', 'TEMPLATE_DIR'] as $constant) {
            if (defined($constant)) {
                $path = rtrim((string)constant($constant), '/\\');
                if ($path !== '') {
                    $message = str_ireplace([$path, str_replace('\\', '/', $path)], '[project]', $message);
                }
            }
        }
        $message = preg_replace(
            '/\b(password|passwd|secret|authorization|cookie|session|csrf|token)\b\s*[:=]\s*[^\s,;]+/iu',
            '$1=[hidden]',
            $message,
        ) ?? $message;
        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($message, 'UTF-8') > 900) {
                $message = mb_substr($message, 0, 897, 'UTF-8') . '…';
            }
        } elseif (strlen($message) > 900) {
            $message = substr($message, 0, 897) . '...';
        }
        return $message !== '' ? $message : 'Причина не была передана исключением.';
    }
}
