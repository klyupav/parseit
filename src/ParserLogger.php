<?php
/**
 * Created by PhpStorm.
 * User: klyupav
 * Date: 19.03.17
 * Time: 13:56
 */

namespace ParseIt;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class ParserLogger
{
    public static function logToFile($message, $level = 'info', $context = [])
    {
        // create a log channel
        $log = new Logger('ParseIt');
        $today = date('Y-m-d');
        $log->pushHandler(new StreamHandler( __DIR__."/../../../../logs/{$today}.log"));
        switch ( $level )
        {
            case 'info':
                $log->info($message, $context);
                break;
            case 'warning':
                $log->warning($message, $context);
                break;
            case 'error':
                $log->error($message, $context);
                break;
        }
    }
}