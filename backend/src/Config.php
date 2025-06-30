<?php
namespace App;

use Dotenv\Dotenv;

class Config {
    private static $instance = null;

    private function __construct() {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }

    public static function get($key, $default = null) {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return $_ENV[$key] ?? $default;
    }
}