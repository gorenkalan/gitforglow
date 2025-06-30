<?php
namespace App;

class Cache {
    private $cacheDir;

    public function __construct() {
        $this->cacheDir = __DIR__ . '/../cache/';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0775, true);
        }
    }

    public function get($key) {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires_at'] > time()) {
                return $data['content'];
            }
            // Cache expired, remove it
            unlink($file);
        }
        return null;
    }

    public function set($key, $content, $ttl = 3600) { // Default TTL: 1 hour
        $file = $this->cacheDir . md5($key) . '.cache';
        $data = [
            'expires_at' => time() + $ttl,
            'content' => $content,
        ];
        file_put_contents($file, serialize($data), LOCK_EX);
    }

    public function forget($key) {
        $file = $this->cacheDir . md5($key) . '.cache';
        if (file_exists($file)) {
            unlink($file);
        }
    }
}