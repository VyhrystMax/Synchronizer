<?php
/**
 * Created by PhpStorm.
 * User: maks
 * Date: 31.01.17
 * Time: 13:17
 */

namespace Synchronizer\Classes;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

/**
 * Class Log
 * @package Synchronizer\Classes
 */
trait Log
{
    /**
     * @param $message
     * @param $type
     */
    public function log($message, $type)
    {
        $logger = new Logger('Synchronizer');
        $logger->pushHandler(new StreamHandler(APP_DIR . 'synchronizer.log', Logger::INFO));

        $logger->{$type}($message);
    }
}