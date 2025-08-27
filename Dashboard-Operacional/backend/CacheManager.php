<?php

class CacheManager {
    private $cacheDir;
    private $defaultTTL = 300; // 5 minutos
    
    public function __construct($cacheDir = 'cache') {
        $this->cacheDir = $cacheDir;
        $this->ensureCacheDir();
    }
    
    private function ensureCacheDir() {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }
    
    private function getCacheFilePath($key) {
        return $this->cacheDir . '/' . md5($key) . '.cache';
    }
    
    public function get($key) {
        $filePath = $this->getCacheFilePath($key);
        
        if (!file_exists($filePath)) {
            return null;
        }
        
        $data = file_get_contents($filePath);
        $cached = json_decode($data, true);
        
        if (!$cached || !isset($cached['expires']) || $cached['expires'] < time()) {
            $this->delete($key);
            return null;
        }
        
        return $cached['data'];
    }
    
    public function set($key, $data, $ttl = null) {
        $ttl = $ttl ?? $this->defaultTTL;
        $filePath = $this->getCacheFilePath($key);
        
        $cached = [
            'data' => $data,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        return file_put_contents($filePath, json_encode($cached)) !== false;
    }
    
    public function delete($key) {
        $filePath = $this->getCacheFilePath($key);
        if (file_exists($filePath)) {
            return unlink($filePath);
        }
        return true;
    }
    
    public function clear() {
        $files = glob($this->cacheDir . '/*.cache');
        foreach ($files as $file) {
            unlink($file);
        }
    }
    
    public function isExpired($key) {
        $filePath = $this->getCacheFilePath($key);
        
        if (!file_exists($filePath)) {
            return true;
        }
        
        $data = file_get_contents($filePath);
        $cached = json_decode($data, true);
        
        return !$cached || !isset($cached['expires']) || $cached['expires'] < time();
    }
}