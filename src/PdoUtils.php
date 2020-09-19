<?php
namespace Jgauthi\Component\Database;

use DateTimeInterface;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class PdoUtils
{
    private const CHARSET = 'utf8';
    private const COLLATE = 'utf8_unicode_ci';

    /**
     * @throws \PDOException
     */
    static public function mysql_init(string $server, string $login, string $pass, string $database, int $port = 3306): PDO
    {
        if (!class_exists('pdo')) {
            throw new InvalidArgumentException('[PDO] No install in current server');
        } elseif (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            throw new InvalidArgumentException('[PDO] php extension pdo_mysql no install');
        }

        $pdo = new PDO("mysql:dbname={$database};host={$server};port={$port}", $login, $pass, [
            PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
            PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_INIT_COMMAND    => 'SET NAMES '. self::CHARSET .' COLLATE '.self::COLLATE,
        ]);

        return $pdo;
    }

    /**
     * @throws \PDOException
     */
    static public function sqlite_init(string $file, string $charset = 'UTF-8'): PDO
    {
        if (!class_exists('pdo')) {
            throw new InvalidArgumentException('[PDO] No install in current server');
        } elseif (!class_exists('SQLite3')) {
            throw new InvalidArgumentException('[PDO] SQLite3 no install');
        }

        $pdo = new PDO("sqlite:{$file}", 'charset='.$charset);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // We can now log any exceptions on Fatal error.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    static public function sqlite_version(PDO $pdo): string
    {
        return $pdo->query('select sqlite_version()')->fetch(PDO::FETCH_NUM)[0];
    }

    static public function resetAutoIncrement(PDO $pdo, string $table, int $id = 0): bool
    {
        if (empty($id)) {
            $stmt = $pdo->query("SHOW KEYS FROM {$table} WHERE Key_name = 'PRIMARY'");
            $primaryKey = $stmt->fetchColumn(4);

            $stmt = $pdo->query("SELECT MAX($primaryKey) FROM {$table} LIMIT 1");
            $id = $stmt->fetchColumn(0);
            $id = ((!empty($id)) ? ($id + 1) : 1);
        }

        return $pdo->exec("ALTER TABLE {$table} AUTO_INCREMENT = {$id}");
    }

    static private function requestWithBinding(PDO $pdo, string $queryStart, string $table, array $data): bool
    {
        $keys = array_keys($data);
        $fields = '`'.implode('`, `', $keys).'`';
        $values = ':'.implode(', :', $keys).'';

        $stmt = $pdo->prepare("{$queryStart} {$table} ({$fields}) VALUES ({$values})");
        self::stmtBindValues($stmt, $data);

        return $stmt->execute();
    }

    static public function stmtBindValues(PDOStatement $stmt, array $data): void
    {
        foreach ($data as $key => $value) {
            $type = PDO::PARAM_STR;
            if (is_int($value)) {
                $type = PDO::PARAM_INT;
            } elseif (is_bool($value)) {
                $type = PDO::PARAM_BOOL;
            } elseif (is_null($value)) {
                $type = PDO::PARAM_NULL;
            } elseif ($value instanceof DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $stmt->bindValue(":$key", $value, $type);
        }
    }

    /**
     * @throws \PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    static public function insert(PDO $pdo, string $table, array $data): ?int
    {
        if (!self::requestWithBinding($pdo, 'INSERT INTO', $table, $data)) {
            return null;
        }

        return $pdo->lastInsertId();
    }

    /*
    static public function old_insert(PDO $pdo, string $table, array $data)
    {
        $keys = array_keys($data);
        $fields = '`'.implode('`, `',$keys).'`';

        $placeholder = substr(str_repeat('?,', count($keys)),0,-1);

        return $pdo
            ->prepare("INSERT INTO {$table} ({$fields}) VALUES ({$placeholder})")
            ->execute(array_values($data))
        ;
    }
    */

    /**
     * @throws \PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    static public function replace(PDO $pdo, string $table, array $data): bool
    {
        return self::requestWithBinding($pdo, 'REPLACE INTO', $table, $data);
    }


    /**
     * @throws \PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    static public function update(PDO $pdo, string $table, array $data, array $where): ?int
    {
        $fields = [];
        array_walk($data, function ($val, $key) use (&$fields) {
            $fields[] = "$key = :$key";
        });
        $fields = implode(', ', $fields);

        $search = [];
        array_walk($where, function ($val, $key) use (&$search) {
            $search[] = "$key = :$key";
        });
        $search = implode(' AND ', $search);

        $stmt = $pdo->prepare("UPDATE {$table} SET {$fields} WHERE {$search}");
        self::stmtBindValues($stmt, ($data + $where));

        return $stmt->execute();
    }
}