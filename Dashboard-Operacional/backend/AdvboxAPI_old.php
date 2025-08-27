<?php

require_once __DIR__ . '/CacheManager.php';

class AdvboxAPI {
    private $baseUrl = 'https://app.advbox.com.br/api/v1';
    private $apiKey = '';
    private $cache;
    private $requestDelay = 2; // 2 segundos entre requests para evitar rate limit
    private static $lastRequestTime = 0;
    
    public function __construct() {
        $this->cache = new CacheManager();
    }
    
    private function makeRequest($endpoint, $params = [], $useCache = true, $cacheTTL = 300) {
        $cacheKey = 'api_' . $endpoint . '_' . md5(serialize($params));
        
        // Tentar buscar do cache primeiro
        if ($useCache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }
        
        // Rate limiting - aguardar entre requests
        $currentTime = microtime(true);
        $timeSinceLastRequest = $currentTime - self::$lastRequestTime;
        if ($timeSinceLastRequest < $this->requestDelay) {
            usleep(($this->requestDelay - $timeSinceLastRequest) * 1000000);
        }
        self::$lastRequestTime = microtime(true);
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_error($ch)) {
            curl_close($ch);
            throw new Exception('Erro cURL: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erro HTTP: ' . $httpCode . ' - Rate limit atingido, aguarde alguns minutos');
        }
        
        $data = json_decode($response, true);
        
        // Salvar no cache se a requisição foi bem-sucedida
        if ($useCache && $data) {
            $this->cache->set($cacheKey, $data, $cacheTTL);
        }
        
        return $data;
    }
    
    public function getSettings() {
        return $this->makeRequest('/settings');
    }
    
    public function getUsers() {
        $settings = $this->getSettings();
        $users = $settings['users'] ?? [];
        
        // Adicionar foto do usuário
        foreach ($users as &$user) {
            $user['photo'] = isset($user['id']) ? 
                'https://ui-avatars.com/api/?name=' . urlencode($user['name'] ?? 'User') . '&size=300&background=3b82f6&color=ffffff&bold=true' :
                null;
        }
        
        return $users;
    }
    
    public function getTasks() {
        $settings = $this->getSettings();
        return $settings['tasks'] ?? [];
    }
    
    public function getTasksCompleted($startDate = null, $endDate = null, $limit = 1000) {
        $params = [
            'limit' => $limit,
            'completed_start' => $startDate ?? date('Y-m-01'),
            'completed_end' => $endDate ?? date('Y-m-d')
        ];
        
        return $this->makeRequest('/posts', $params);
    }
    
    public function getUserPerformance($userId = null) {
        // Tentar puxar performance específica do usuário
        if ($userId) {
            return $this->makeRequest("/users/{$userId}/performance");
        }
        
        // Puxar performance geral
        return $this->makeRequest('/performance');
    }
    
    public function getTopPerformers($limit = 20) {
        try {
            $performance = $this->makeRequest('/performance/ranking', ['limit' => $limit]);
            return $performance['data'] ?? [];
        } catch (Exception $e) {
            // Fallback: calcular performance baseado em posts
            return $this->calculatePerformanceFromPosts($limit);
        }
    }
    
    private function calculatePerformanceFromPosts($limit = 20) {
        $posts = $this->getTasksCompleted();
        $users = $this->getUsers();
        $userPerformance = [];
        
        // Inicializar performance dos usuários
        foreach ($users as $user) {
            $userPerformance[$user['id']] = [
                'user' => $user,
                'performance' => [
                    'total_points' => 0,
                    'completed_tasks' => 0,
                    'current_month_points' => 0
                ]
            ];
        }
        
        // Calcular pontos dos posts
        if (isset($posts['data'])) {
            foreach ($posts['data'] as $post) {
                $userId = $post['user_id'] ?? null;
                $points = $post['points'] ?? 0;
                
                if ($userId && isset($userPerformance[$userId])) {
                    $userPerformance[$userId]['performance']['total_points'] += $points;
                    $userPerformance[$userId]['performance']['completed_tasks']++;
                    $userPerformance[$userId]['performance']['current_month_points'] += $points;
                }
            }
        }
        
        // Ordenar por pontos e retornar top performers
        usort($userPerformance, function($a, $b) {
            return $b['performance']['total_points'] - $a['performance']['total_points'];
        });
        
        return array_slice($userPerformance, 0, $limit);
    }
    
    public function getUserTasks($userId, $startDate = null, $endDate = null, $limit = 50) {
        $params = [
            'user_id' => $userId,
            'limit' => $limit,
            'completed_start' => $startDate ?? date('Y-m-01'),
            'completed_end' => $endDate ?? date('Y-m-d')
        ];
        
        return $this->makeRequest('/posts', $params);
    }
    
    // Função removida - estava duplicada
    
    public function getTopPerformers($limit = 10, $period = 30) {
        try {
            $performance = $this->makeRequest('/performance/ranking', ['limit' => $limit]);
            return $performance['data'] ?? [];
        } catch (Exception $e) {
            // Fallback: calcular performance baseado em posts
            return $this->calculatePerformanceFromPosts($limit);
        }
    }
}