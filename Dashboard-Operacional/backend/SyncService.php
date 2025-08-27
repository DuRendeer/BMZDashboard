<?php
require_once __DIR__ . '/DatabaseManager.php';
require_once __DIR__ . '/AdvboxAPI.php';
require_once __DIR__ . '/config.php';

class SyncService {
    private $db;
    private $api;
    private $config;
    
    public function __construct() {
        $this->db = new DatabaseManager();
        $this->api = new AdvboxAPI();
        
        // Configurar API key do banco se disponível
        $apiKey = $this->db->getConfig('api_key');
        if ($apiKey) {
            $this->api->setApiKey($apiKey);
        }
    }
    
    public function syncAll($syncType = 'manual') {
        $logId = $this->db->startSyncLog($syncType);
        $recordsProcessed = 0;
        $errors = [];
        
        try {
            echo "🔄 Iniciando sincronização completa...\n";
            
            // 1. Sincronizar usuários
            echo "📥 Sincronizando usuários...\n";
            $usersCount = $this->syncUsers();
            $recordsProcessed += $usersCount;
            echo "✅ {$usersCount} usuários sincronizados\n";
            
            // 2. Sincronizar tarefas
            echo "📥 Sincronizando tarefas...\n";
            $tasksCount = $this->syncTasks();
            $recordsProcessed += $tasksCount;
            echo "✅ {$tasksCount} tarefas sincronizadas\n";
            
            // 3. Sincronizar tarefas completadas
            echo "📥 Sincronizando tarefas completadas...\n";
            $completedCount = $this->syncTasksCompleted();
            $recordsProcessed += $completedCount;
            echo "✅ {$completedCount} tarefas completadas sincronizadas\n";
            
            // 4. Calcular performance
            echo "🔢 Calculando performance dos usuários...\n";
            $this->calculateUserPerformance();
            echo "✅ Performance calculada\n";
            
            // 5. Atualizar timestamp da última sincronização
            $this->db->setConfig('last_sync', date('Y-m-d H:i:s'));
            
            $this->db->completeSyncLog($logId, $recordsProcessed);
            
            echo "✅ Sincronização completa! {$recordsProcessed} registros processados\n";
            
            return [
                'success' => true,
                'records_processed' => $recordsProcessed,
                'message' => "Sincronização concluída com sucesso"
            ];
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            $this->db->completeSyncLog($logId, $recordsProcessed, $errorMessage);
            
            echo "❌ Erro na sincronização: {$errorMessage}\n";
            
            return [
                'success' => false,
                'error' => $errorMessage,
                'records_processed' => $recordsProcessed
            ];
        }
    }
    
    private function syncUsers() {
        $users = $this->api->getUsers();
        $count = 0;
        
        foreach ($users as $user) {
            $userData = [
                'id' => $user['id'],
                'name' => $user['name'] ?? '',
                'email' => $user['email'] ?? '',
                'phone' => $user['phone'] ?? '',
                'photo' => $user['photo'] ?? null
            ];
            
            if ($this->db->insertOrUpdateUser($userData)) {
                $count++;
            }
            
            // Pequeno delay para evitar sobrecarga
            usleep(50000); // 0.05 segundos
        }
        
        return $count;
    }
    
    private function syncTasks() {
        $tasks = $this->api->getTasks();
        $count = 0;
        
        foreach ($tasks as $task) {
            $taskData = [
                'id' => $task['id'],
                'title' => $task['title'] ?? $task['name'] ?? '',
                'description' => $task['description'] ?? '',
                'points' => $task['points'] ?? 0,
                'category' => $task['category'] ?? 'general'
            ];
            
            if ($this->db->insertOrUpdateTask($taskData)) {
                $count++;
            }
            
            usleep(50000);
        }
        
        return $count;
    }
    
    private function syncTasksCompleted() {
        $completed = $this->api->getTasksCompleted();
        $count = 0;
        
        echo "📊 Dados da API: " . json_encode($completed) . "\n"; // Debug
        
        if (isset($completed['data']) && is_array($completed['data'])) {
            foreach ($completed['data'] as $task) {
                $taskData = [
                    'user_id' => $task['user_id'] ?? ($task['author_id'] ?? 0),
                    'task_id' => $task['task_id'] ?? ($task['id'] ?? 0),
                    'points_earned' => $task['points'] ?? ($task['score'] ?? 0),
                    'completed_at' => $task['completed_at'] ?? ($task['created_at'] ?? date('Y-m-d H:i:s'))
                ];
                
                echo "📝 Task processado: " . json_encode($taskData) . "\n"; // Debug
                
                // Inserir mesmo se não tiver task_id específico
                if ($taskData['user_id'] > 0) {
                    if ($this->db->insertTaskCompleted($taskData)) {
                        $count++;
                    }
                }
                
                usleep(50000);
            }
        }
        
        return $count;
    }
    
    private function calculateUserPerformance() {
        $users = $this->db->getUsers();
        $monthlyGoal = (int) $this->db->getConfig('monthly_points_goal') ?: 1000;
        
        foreach ($users as $user) {
            $userId = $user['id'];
            
            // Buscar tarefas completadas do usuário
            $completedTasks = $this->db->getTasksCompleted($userId);
            
            $totalPoints = 0;
            $totalTasks = count($completedTasks);
            $currentMonthPoints = 0;
            $currentMonthTasks = 0;
            $lastActivity = null;
            
            $currentMonth = date('Y-m');
            
            foreach ($completedTasks as $task) {
                $points = $task['points_earned'] ?? 0;
                $totalPoints += $points;
                
                $completedMonth = date('Y-m', strtotime($task['completed_at']));
                if ($completedMonth === $currentMonth) {
                    $currentMonthPoints += $points;
                    $currentMonthTasks++;
                }
                
                if (!$lastActivity || $task['completed_at'] > $lastActivity) {
                    $lastActivity = $task['completed_at'];
                }
            }
            
            // Calcular progresso da meta
            $goalProgress = ($currentMonthPoints / $monthlyGoal) * 100;
            
            // Determinar nível de performance
            $performanceLevel = $this->getPerformanceLevel($goalProgress);
            
            $performanceData = [
                'user_id' => $userId,
                'total_points' => $totalPoints,
                'completed_tasks' => $totalTasks,
                'current_month_points' => $currentMonthPoints,
                'current_month_tasks' => $currentMonthTasks,
                'last_activity' => $lastActivity ?: date('Y-m-d H:i:s'),
                'performance_level' => $performanceLevel,
                'goal_progress' => round($goalProgress, 2)
            ];
            
            $this->db->updateUserPerformance($userId, $performanceData);
            
            usleep(10000);
        }
    }
    
    private function getPerformanceLevel($goalProgress) {
        if ($goalProgress >= 100) return 'excellent';
        if ($goalProgress >= 75) return 'very_good';
        if ($goalProgress >= 50) return 'good';
        if ($goalProgress >= 25) return 'regular';
        return 'critical';
    }
    
    public function getLastSyncInfo() {
        $lastSync = $this->db->getLastSyncTime();
        $logs = $this->db->getSyncLogs(1);
        
        return [
            'last_sync' => $lastSync,
            'last_log' => $logs[0] ?? null,
            'time_since_sync' => $lastSync ? $this->getTimeSince($lastSync) : 'Nunca'
        ];
    }
    
    private function getTimeSince($datetime) {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return $time . ' segundos atrás';
        if ($time < 3600) return floor($time/60) . ' minutos atrás';
        if ($time < 86400) return floor($time/3600) . ' horas atrás';
        return floor($time/86400) . ' dias atrás';
    }
    
    public function shouldAutoSync() {
        $autoSyncEnabled = $this->db->getConfig('auto_sync_enabled') === '1';
        if (!$autoSyncEnabled) return false;
        
        $lastSync = $this->db->getLastSyncTime();
        if (!$lastSync) return true;
        
        $interval = (int) $this->db->getConfig('auto_sync_interval') ?: 30;
        $timeSinceSync = (time() - strtotime($lastSync)) / 60; // em minutos
        
        return $timeSinceSync >= $interval;
    }
    
    public function quickSync() {
        // Sincronização rápida - apenas performance dos usuários existentes
        try {
            $logId = $this->db->startSyncLog('automatic');
            $this->calculateUserPerformance();
            $this->db->setConfig('last_sync', date('Y-m-d H:i:s'));
            $this->db->completeSyncLog($logId, 0);
            
            return ['success' => true, 'message' => 'Sincronização rápida concluída'];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}