<?php
/*****************************************************************************************************
 * @name PdoTableConfiguration
 * @note: Management of a configuration table, inspired by the `wp_options` table (wordpress)
 * @author: Jgauthi <github.com/jgauthi>, created at [19jun2018]
 * @note: SQL queries are mysql and sqlite compatible (pay attention to the syntax)

 ******************************************************************************************************/
namespace Jgauthi\Component\Database;

use PDO;

class PdoTableConfiguration
{
    /** @var PDO */
    private $pdo;
    private $table;

    /**
     * PdoTableConfiguration constructor.
     * @param PDO $pdo
     * @param string $table
     */
    public function __construct(PDO $pdo, $table = 'configuration')
    {
        $this->pdo = $pdo;
        $this->table = $table;
    }

    /**
     * Install the necessary table for this script
     * @param bool $dropTableIfExist
     * @return bool
     * @throws \Exception
     * @throws \PDOException
     */
    public function install($dropTableIfExist = false)
    {
        $query = [];
        if ($dropTableIfExist) {
            $query[] = "DROP TABLE IF EXISTS `{$this->table}`;";
        }

        $pdoDriver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        if ($pdoDriver == 'mysql') {
            $query[] = "
            CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `name` varchar(100) NOT NULL,
                `value` text,
                `serialize` tinyint UNSIGNED NOT NULL DEFAULT '0',
                `dateUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`name`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COMMENT='Various variables and options for the application';";

        } elseif ($pdoDriver == 'sqlite') {
            $query[] = "
            CREATE TABLE IF NOT EXISTS `{$this->table}` (
                `name` varchar(100) NOT NULL,
                `value` text,
                `serialize` tinyint UNSIGNED NOT NULL DEFAULT '0',
                `dateUpdate` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`name`)
            )";

            // Replace ON UPDATE by a trigger (this syntax work only on mysql)
            $query[] = "
            CREATE TRIGGER IF NOT EXISTS UpdateLastTime UPDATE OF value, serialize ON {$this->table}
                BEGIN
                  UPDATE {$this->table} SET dateUpdate=CURRENT_TIMESTAMP WHERE name = NEW.name;
                END;
            ";

        } else {
            throw new \Exception("$pdoDriver not supported");
        }

        foreach ($query as $req) {
            $this->pdo->exec($req);
        }

        return true;
    }

    /**
     * @param string $name
     * @param mixed|null $defaultValue
     * @return mixed|null
     */
    public function get($name, $defaultValue = null)
    {
        $query = $this->pdo->prepare("
                SELECT value, serialize
                FROM `{$this->table}`
                WHERE name = :name
                LIMIT 1
            ");

        $query->execute(['name' => $name]);
        $result = $query->fetch(PDO::FETCH_ASSOC);

        if (!isset($result['value'])) {
            return $defaultValue;
        }
        return ($result['serialize']) ? unserialize($result['value']) : $result['value'];
    }

    /**
     * @param string $name
     * @param mixed $value
     * @return self
     */
    public function save($name, $value)
    {
        $params = [
            'name' => $name,
            'value' => $value,
            'serialize' => 0,
        ];

        if (is_array($value) || is_object($value)) {
            $params['serialize'] = 1;
            $params['value'] = serialize($value);
        }

        $sql = "REPLACE INTO `{$this->table}` (name, value, serialize)
                    VALUES (:name, :value, :serialize)
        ";

        $this->pdo->prepare($sql)->execute($params);
        return $this;
    }

    /**
     * @param string $name
     * @return self
     */
    public function delete($name)
    {
        $this->pdo
            ->prepare("DELETE FROM `{$this->table}` WHERE name = :name LIMIT 1")
            ->execute(['name' => $name]);

        return $this;
    }
}