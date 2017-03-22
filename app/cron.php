<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 17.01.17
 * Time: 17:13
 */

require_once(__DIR__ . '/../vendor/autoload.php');

$config    = include_once('settings.php');
$db_config = (object)$config['database'];

$connection = new \Simplon\Mysql\Mysql(
    $db_config->host,
    $db_config->user,
    $db_config->password,
    $db_config->database,
    \PDO::FETCH_OBJ
);

$syncer = new \Synchronizer\Synchronizer(
    new \Synchronizer\Classes\AdWordsAPI($config['path_to_ini']),
    new \Synchronizer\Classes\Database(
        $connection,
        $config['base_table'],
        $config['aw_table']
    ),
    new \Synchronizer\Classes\DataMapper(),
    new \Synchronizer\Classes\DataProcessor()
);

$syncer->synchronize();