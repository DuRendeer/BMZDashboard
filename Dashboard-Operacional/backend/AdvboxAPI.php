<?php

class AdvboxAPI {
    private $baseUrl = 'https://app.advbox.com.br/api/v1';
    private $apiKey = '';
    private $cache = []; // Cache simples em memória
    
    public function __construct() {
        // Cache simples sem arquivos
    }
    
    private function makeRequest($endpoint, $params = []) {
        $url = $this->baseUrl . $endpoint;
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $cacheKey = md5($url);
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        
        sleep(2); // Rate limiting
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception('Erro HTTP: ' . $httpCode);
        }
        
        $data = json_decode($response, true);
        $this->cache[$cacheKey] = $data; // Cache simples
        
        return $data;
    }
    
    public function getSettings() {
        return $this->makeRequest('/settings');
    }
    
    public function getUsers() {
        $settings = $this->getSettings();
        $users = $settings['users'] ?? [];
        
        foreach ($users as &$user) {
            $user['photo'] = 'https://s3-sa-east-1.amazonaws.com/advbox/files/profiles/' . $user['id'] . '.jpg';
        }
        
        return $users;
    }
    
    public function getTasks() {
        $settings = $this->getSettings();
        return $settings['tasks'] ?? [];
    }
    
    public function getTasksCompleted($limit = 1000) {
        return $this->makeRequest('/posts', [
            'limit' => $limit,
            'completed_start' => date('Y-m-01'),
            'completed_end' => date('Y-m-d')
        ]);
    }
    
    public function getTopPerformers($limit = 20) {
        $users = $this->getUsers();
        $posts = $this->getTasksCompleted();
        
        // Conectar ao banco para buscar metas personalizadas
        require_once 'DatabaseManager.php';
        $db = new DatabaseManager();
        
        $performance = [];
        
        // Inicializar usuários
        foreach ($users as $user) {
            $performance[$user['id']] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'] ?? '',
                'photo' => $user['photo'] ?? '',
                'total_points' => 0,
                'completed_tasks' => 0,
                'current_month_points' => 0,
                'goal_progress' => 0,
                'performance_level' => 'critical',
                'performance_label' => 'Crítico'
            ];
        }
        
        // Calcular pontos
        if (isset($posts['data'])) {
            foreach ($posts['data'] as $post) {
                $points = $post['reward'] ?? 0;
                
                // Posts têm array 'users' com user_id
                if (isset($post['users']) && !empty($post['users'])) {
                    foreach ($post['users'] as $user) {
                        $userId = $user['user_id'];
                        
                        if ($userId && isset($performance[$userId])) {
                            $performance[$userId]['total_points'] += $points;
                            $performance[$userId]['completed_tasks']++;
                            $performance[$userId]['current_month_points'] += $points;
                        }
                    }
                }
            }
        }
        
        // Classificar e ordenar usando metas personalizadas
        foreach ($performance as &$perf) {
            // Buscar meta personalizada do usuário
            $userGoal = $db->getUserGoal($perf['id']);
            $progress = ($perf['total_points'] / $userGoal) * 100;
            $perf['goal_progress'] = round($progress, 2);
            $perf['user_goal'] = $userGoal;
            
            if ($progress >= 100) {
                $perf['performance_level'] = 'excellent';
                $perf['performance_label'] = 'Excelente';
            } elseif ($progress >= 75) {
                $perf['performance_level'] = 'very_good';
                $perf['performance_label'] = 'Muito Bom';
            } elseif ($progress >= 50) {
                $perf['performance_level'] = 'good';
                $perf['performance_label'] = 'Bom';
            } elseif ($progress >= 25) {
                $perf['performance_level'] = 'regular';
                $perf['performance_label'] = 'Regular';
            }
        }
        
        usort($performance, function($a, $b) {
            return $b['total_points'] - $a['total_points'];
        });
        
        return array_slice(array_values($performance), 0, $limit);
    }
}