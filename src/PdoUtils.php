<?php
namespace Jgauthi\Component\Database;

use DateTimeInterface;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class PdoUtils
{
    const CHARSET = 'utf8';
    const COLLATE = 'utf8_unicode_ci';

    /**
     * @param string $server
     * @param string $login
     * @param string $pass
     * @param string $database
     * @param int $port
     * @return PDO
     * @throws \PDOException
     */
    static public function mysql_init($server, $login, $pass, $database, $port = 3306)
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
     * @param string $file
     * @param string $charset
     * @return PDO
     * @throws \PDOException
     */
    static public function sqlite_init($file, $charset = 'UTF-8')
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

    /**
     * @param PDO $pdo
     * @return string
     */
    static public function sqlite_version(PDO $pdo)
    {
        return $pdo->query('select sqlite_version()')->fetch(PDO::FETCH_NUM)[0];
    }

    /**
     * @param PDO $pdo
     * @param string $table
     * @param int $id
     * @return bool
     */
    static public function resetAutoIncrement(PDO $pdo, $table, $id = 0)
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

    /**
     * @param PDO $pdo
     * @param string $queryStart
     * @param string $table
     * @param array $data
     * @return bool
     */
    static private function requestWithBinding($pdo, $queryStart, $table, $data)
    {
        $keys = array_keys($data);
        $fields = '`'.implode('`, `', $keys).'`';
        $values = ':'.implode(', :', $keys).'';

        $stmt = $pdo->prepare("{$queryStart} {$table} ({$fields}) VALUES ({$values})");
        self::stmtBindValues($stmt, $data);

        return $stmt->execute();
    }

    /**
     * @param PDOStatement $stmt
     * @param array $data
     */
    static public function stmtBindValues(PDOStatement $stmt, $data)
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
     * @param PDO $pdo
     * @param string $table
     * @param array $data
     * @return int|null
     * @throws \PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    static public function insert(PDO $pdo, $table, $data)
    {
        if (!self::requestWithBinding($pdo, 'INSERT INTO', $table, $data)) {
            return null;
        }

        return $pdo->lastInsertId();
    }

    /**
     * @param PDO $pdo
     * @param string $table
     * @param array $data
     * @return bool
     * @throws \PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    static public function replace(PDO $pdo, $table, $data)
    {
        return self::requestWithBinding($pdo, 'REPLACE INTO', $table, $data);
    }

    /**
     * @param PDO $pdo
     * @param string $table
     * @param array $data
     * @param array $where
     * @return int|null
     * @throws \PDOException On error if PDO::ERRMODE_EXCEPTION option is true.
     */
    static public function update(PDO $pdo, $table, $data, $where)
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