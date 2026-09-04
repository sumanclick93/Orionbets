<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private static ?self $instance = null;
    private ?PDO $pdo = null;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function pdo(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        $configuredHost = Env::get('DB_HOST', '127.0.0.1') ?: '127.0.0.1';
        $port = Env::get('DB_PORT', '3306');
        $name = Env::get('DB_DATABASE', 'edgeplay');
        $user = Env::get('DB_USERNAME', 'root');
        $pass = Env::get('DB_PASSWORD', '');

        $hosts = array_values(array_unique([$configuredHost, '127.0.0.1', 'localhost']));
        $ports = array_values(array_unique([$port, '3306', '3307']));
        $credentials = [
            ['user' => $user, 'pass' => $pass],
            ['user' => 'root', 'pass' => ''],
            ['user' => 'edgeplay', 'pass' => 'edgeplay'],
        ];

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $lastError = 'Unknown database error.';
        foreach ($hosts as $h) {
            foreach ($ports as $p) {
                foreach ($credentials as $cred) {
                    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $h, $p, $name);
                    try {
                        $this->pdo = new PDO($dsn, $cred['user'], $cred['pass'], $options);
                        return $this->pdo;
                    } catch (PDOException $e) {
                        $lastError = $e->getMessage();
                    }
                }
            }
        }

        throw new RuntimeException('Unable to connect to the database. ' . $lastError);
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function query(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    public function fetchColumn(string $sql, array $params = []): mixed
    {
        return $this->query($sql, $params)->fetchColumn();
    }

    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(static fn ($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $this->query($sql, $data);
        return (int) $this->pdo()->lastInsertId();
    }

    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $sets = [];
        $params = [];

        foreach ($data as $column => $value) {
            $sets[] = sprintf('`%s` = :set_%s', $column, $column);
            $params['set_' . $column] = $value;
        }

        $sql = sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $sets), $where);
        $stmt = $this->query($sql, array_merge($params, $whereParams));

        return $stmt->rowCount();
    }

    public function delete(string $table, string $where, array $params = []): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        return $this->query($sql, $params)->rowCount();
    }

    public function tableExists(string $table): bool
    {
        try {
            $row = $this->fetch(
                'SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table',
                ['table' => $table]
            );
            return (int) ($row['c'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function columnExists(string $table, string $column): bool
    {
        try {
            $row = $this->fetch(
                'SELECT COUNT(*) AS c FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table AND column_name = :column',
                ['table' => $table, 'column' => $column]
            );
            return (int) ($row['c'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    public function indexExists(string $table, string $index): bool
    {
        try {
            $row = $this->fetch(
                'SELECT COUNT(*) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = :table AND index_name = :idx',
                ['table' => $table, 'index' => $index]
            );
            return (int) ($row['c'] ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
}
