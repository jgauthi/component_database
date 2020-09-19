<?php
use Jgauthi\Component\Database\{PdoAlice, PdoUtils};
use Nelmio\Alice\Loader\NativeLoader;

// Conf
define('FAKER_LOCALISATION', 'fr_FR');

// In this example, the vendor folder is located in "example/"
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/inc/config.inc.php';

/* -- INSTALL THIS TABLE BEFORE TEST
CREATE TABLE `user` (
  `id` int(11) UNSIGNED NOT NULL,
  `idclef` varchar(20) NOT NULL DEFAULT '',
  `niveau` varchar(20) NOT NULL DEFAULT '',
  `login` varchar(32) NOT NULL DEFAULT '',
  `password` varchar(32) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `date_creation` date NOT NULL,
  `date_lastpass` date DEFAULT NULL,
  `page` varchar(50) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

ALTER TABLE `user`
  ADD PRIMARY KEY (`id`),
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1;
*/

$pdo = PdoUtils::mysql_init(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$alice = new PdoAlice(new NativeLoader, $pdo);
$alice->loadFile(__DIR__ . '/inc/alice_users.yaml')->execute();

var_dump($alice);
