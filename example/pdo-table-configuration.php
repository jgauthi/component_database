<?php
use Jgauthi\Component\Database\{PdoTableConfiguration, PdoUtils};

// In this example, the vendor folder is located in "example/"
require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/inc/config.inc.php';

// Requête
$pdo = PdoUtils::mysql_init(DB_HOST, DB_USER, DB_PASS, DB_NAME);

try {
    $variable = new PdoTableConfiguration($pdo, 'variable');
    $variable->install();
} catch (Exception $e) {
    die('Error during install: '. $e->getMessage());
}

$variable
    ->save('timestamp', strtotime('2015-05-12 20:00:00'))
    ->save('test_array', ['current_script' => basename($_SERVER['PHP_SELF']), 'hello' => 'world']);

$variable_in_database = $variable->get('timestamp');
var_dump("$variable_in_database => " . date('d/m/Y', $variable_in_database));


// Delete
$variable->delete('test_array');

$variable_dont_exist = $variable->get('test_array', ['current_script' => null, 'hello' => null]);
var_dump('variable_dont_exist (use default value)', $variable_dont_exist);


// Edit variable
$timestamp = time();
$variable->save('timestamp', $timestamp);
var_dump("$timestamp => " . date('d/m/Y', $timestamp));
