<?php
namespace Divido\DividoFinancing\Logger;

class Logger extends \Monolog\Logger
{
    const NAMESPACE = "PoweredByDivido";
    
    public function info(string $message){

        $newMessage = self::NAMESPACE.": {$message}";
        return parent::info($newMessage);
    }

    public function error(string $message){

        $newMessage = self::NAMESPACE.": {$message}";
        return parent::error($newMessage);
    }

    public function debug(string $message){

        $newMessage = self::NAMESPACE.": {$message}";
        return parent::debug($newMessage);
    }

    public function warning(string $message){

        $newMessage = self::NAMESPACE.": {$message}";
        return parent::warning($newMessage);
    }
}