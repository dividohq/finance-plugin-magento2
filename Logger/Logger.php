<?php
namespace Divido\DividoFinancing\Logger;

class Logger extends \Monolog\Logger
{
    const NAMESPACE = "PoweredByDivido";
    
    public function info(string $message, array $context = []){

        $newMessage = self::NAMESPACE.": {$message}";
        $context[] = self::NAMESPACE;
        return parent::info($newMessage, $context);
    }

    public function error(string $message, array $context = []){

        $newMessage = self::NAMESPACE.": {$message}";
        $context[] = self::NAMESPACE;
        return parent::error($newMessage, $context);
    }

    public function debug(string $message, array $context = []){

        $newMessage = self::NAMESPACE.": {$message}";
        $context[] = self::NAMESPACE;
        return parent::debug($newMessage, $context);
    }

    public function warning(string $message, array $context = []){

        $newMessage = self::NAMESPACE.": {$message}";
        $context[] = self::NAMESPACE;
        return parent::warning($newMessage, $context);
    }
}