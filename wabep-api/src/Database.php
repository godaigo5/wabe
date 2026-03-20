<?php

declare(strict_types=1);

namespace WABEP;

use PDO;
use PDOException;

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = require __DIR__ . '/../config/config.php';

        if (!isset($config['db'])) {
            throw new \RuntimeException('Database config not found');
        }

        $db = $config['db'];

        $host    = (string)($db['host'] ?? '127.0.0.1');
        $port    = (int)($db['port'] ?? 3306);
        $name    = (string)($db['name'] ?? '');
        $user    = (string)($db['user'] ?? '');
        $pass    = (string)($db['pass'] ?? '');
        $charset = (string)($db['charset'] ?? 'utf8mb4');

        if ($name === '' || $user === '') {
            throw new \RuntimeException('DB name or user is empty');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $host,
            $port,
            $name,
            $charset
        );

        try {
            self::$pdo = new PDO(
                $dsn,
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            throw new \RuntimeException('DB connection failed: ' . $e->getMessage());
        }

        return self::$pdo;
    }
}
