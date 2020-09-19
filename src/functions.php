<?php
/**
 * @param string $server
 * @param string $login
 * @param string $pass
 * @param string $database
 * @param string $port
 *
 * @return PDO
 */
function mysql_init($server, $login, $pass, $database, $port = 3306)
{
    if (!class_exists('pdo')) {
        throw new InvalidArgumentException('[PDO] No install in current server');
    } elseif (!defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
        throw new InvalidArgumentException('[PDO] php extension pdo_mysql no install');
    }

    $pdo = new PDO("mysql:dbname={$database};host={$server};port={$port}", $login, $pass, [
        PDO::ATTR_DEFAULT_FETCH_MODE    => PDO::FETCH_ASSOC,
        PDO::ATTR_ERRMODE               => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND    => 'SET NAMES utf8 COLLATE utf8_unicode_ci',
    ]);

    return $pdo;
}
