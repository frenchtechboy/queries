<?php
require 'dump.trait.php';
require 'sqlgenerator.trait.php';
require 'queries.php';

define('DUMP', 1);
define('DUMP_DIR', 'dumps/');
define('SQL_CACHE_DIR', 'cache/');

[$host, $db, $user, $pass, $charset] = ['127.0.0.1', 'test', 'test', 'm0li3re', 'utf8mb4'];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];

try {
    $PDO = new PDO($dsn, $user, $pass, $options);
}
catch (PDOException $e) {
    throw new PDOException($e->getMessage(), (int) $e->getCode());
}

$Queries = new Queries($PDO);


echo '<pre>';

var_dump($Queries->table('jeux_video')
                 ->select());

echo '</pre>';

