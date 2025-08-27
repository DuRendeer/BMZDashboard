<?php

class DatabaseManager {
    private $pdo;
    private $config;
    
    public function __construct() {
        $this->config = [
            'host' => '127.0.0.1:3306',
            'dbname' => 'u406174804_teste',
            'username' => 'u406174804_testador',
            'password' => '',
            'charset' => 'utf8mb4'
        ];
        
        $this->connect();
    }
    
    private function connect() {
        try {
            $dsn = "mysql:host={$this->config['host']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
        } catch (PDOException $e) {
            throw new Exception('Erro de conexão com o banco: ' . $e->getMessage());
        }
    }
    
    // Usuários
    public function getUsers($limit = null, $orderBy = 'name') {
        $sql = "SELECT * FROM users ORDER BY {$orderBy}";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getUserById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function insertOrUpdateUser($userData) {
        $sql = "INSERT INTO users (id, name, email, phone, photo) 
                VALUES (:id, :name, :email, :phone, :photo) 
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name), 
                email = VALUES(email), 
                phone = VALUES(phone),
                photo = VALUES(photo)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($userData);
    }
    
    // Tarefas
    public function getTasks() {
        $stmt = $this->pdo->query("SELECT * FROM tasks ORDER BY points DESC");
        return $stmt->fetchAll();
    }
    
    public function insertOrUpdateTask($taskData) {
        $sql = "INSERT INTO tasks (id, title, description, points, category) 
                VALUES (:id, :title, :description, :points, :category) 
                ON DUPLICATE KEY UPDATE 
                title = VALUES(title), 
                description = VALUES(description), 
                points = VALUES(points),
                category = VALUES(category)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($taskData);
    }
    
    // Performance dos usuários
    public function getUserPerformance($limit = null, $orderBy = 'total_points DESC') {
        $sql = "SELECT * FROM user_performance_summary ORDER BY {$orderBy}";
        if ($limit) {
            $sql .= " LIMIT {$limit}";
        }
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    public function getTopPerformers($limit = 20) {
        return $this->getUserPerformance($limit, 'total_points DESC');
    }
    
    public function updateUserPerformance($userId, $performanceData) {
        $sql = "INSERT INTO user_performance 
                (user_id, total_points, completed_tasks, current_month_points, 
                 current_month_tasks, last_activity, performance_level, goal_progress) 
                VALUES (:user_id, :total_points, :completed_tasks, :current_month_points, 
                        :current_month_tasks, :last_activity, :performance_level, :goal_progress) 
                ON DUPLICATE KEY UPDATE 
                total_points = VALUES(total_points),
                completed_tasks = VALUES(completed_tasks),
                current_month_points = VALUES(current_month_points),
                current_month_tasks = VALUES(current_month_tasks),
                last_activity = VALUES(last_activity),
                performance_level = VALUES(performance_level),
                goal_progress = VALUES(goal_progress)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($performanceData);
    }
    
    // Tarefas completadas
    public function insertTaskCompleted($taskCompletedData) {
        $sql = "INSERT INTO user_tasks_completed 
                (user_id, task_id, points_earned, completed_at) 
                VALUES (:user_id, :task_id, :points_earned, :completed_at)
                ON DUPLICATE KEY UPDATE 
                points_earned = VALUES(points_earned)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($taskCompletedData);
    }
    
    public function getTasksCompleted($userId = null, $limit = null) {
        $where = $userId ? "WHERE user_id = {$userId}" : "";
        $limitSql = $limit ? "LIMIT {$limit}" : "";
        
        $sql = "SELECT utc.*, u.name as user_name, t.title as task_title 
                FROM user_tasks_completed utc 
                LEFT JOIN users u ON utc.user_id = u.id 
                LEFT JOIN tasks t ON utc.task_id = t.id 
                {$where} 
                ORDER BY completed_at DESC {$limitSql}";
        
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll();
    }
    
    // Estatísticas do dashboard
    public function getDashboardStats() {
        $stmt = $this->pdo->query("SELECT * FROM dashboard_stats");
        return $stmt->fetch();
    }
    
    // Configurações do sistema
    public function getConfig($key = null) {
        if ($key) {
            $stmt = $this->pdo->prepare("SELECT config_value FROM system_config WHERE config_key = ?");
            $stmt->execute([$key]);
            $result = $stmt->fetch();
            return $result ? $result['config_value'] : null;
        }
        
        $stmt = $this->pdo->query("SELECT * FROM system_config");
        $configs = $stmt->fetchAll();
        
        $result = [];
        foreach ($configs as $config) {
            $result[$config['config_key']] = $config['config_value'];
        }
        return $result;
    }
    
    public function setConfig($key, $value) {
        $sql = "INSERT INTO system_config (config_key, config_value) 
                VALUES (:key, :value) 
                ON DUPLICATE KEY UPDATE 
                config_value = VALUES(config_value)";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['key' => $key, 'value' => $value]);
    }
    
    // Logs de sincronização
    public function startSyncLog($syncType = 'manual') {
        $sql = "INSERT INTO sync_logs (sync_type, status, started_at) VALUES (?, 'started', NOW())";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$syncType]);
        return $this->pdo->lastInsertId();
    }
    
    public function completeSyncLog($logId, $recordsProcessed, $errorMessage = null) {
        $status = $errorMessage ? 'failed' : 'completed';
        $sql = "UPDATE sync_logs SET 
                status = ?, 
                records_processed = ?, 
                error_message = ?, 
                completed_at = NOW(), 
                duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW()) 
                WHERE id = ?";
        
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$status, $recordsProcessed, $errorMessage, $logId]);
    }
    
    public function getSyncLogs($limit = 10) {
        $sql = "SELECT * FROM sync_logs ORDER BY started_at DESC LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }
    
    public function getLastSyncTime() {
        return $this->getConfig('last_sync');
    }
    
    // Métricas e filtros
    public function getUsersByPerformanceLevel($level) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_performance_summary WHERE performance_level = ?");
        $stmt->execute([$level]);
        return $stmt->fetchAll();
    }
    
    public function getUsersAboveGoal() {
        $stmt = $this->pdo->query("SELECT * FROM user_performance_summary WHERE goal_progress >= 100 ORDER BY goal_progress DESC");
        return $stmt->fetchAll();
    }
    
    public function getUsersBelowGoal($threshold = 50) {
        $stmt = $this->pdo->prepare("SELECT * FROM user_performance_summary WHERE goal_progress < ? ORDER BY goal_progress ASC");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll();
    }
    
    // Limpeza de dados antigos
    public function cleanOldSyncLogs($days = 30) {
        $sql = "DELETE FROM sync_logs WHERE started_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$days]);
    }
    
    // Métodos para metas dos usuários
    public function getUserGoal($userId, $goalType = 'monthly_points') {
        $stmt = $this->pdo->prepare("SELECT goal_value FROM user_goals 
                                     WHERE user_id = ? AND goal_type = ? 
                                     AND (end_date IS NULL OR end_date >= CURRENT_DATE) 
                                     ORDER BY start_date DESC LIMIT 1");
        $stmt->execute([$userId, $goalType]);
        $result = $stmt->fetch();
        return $result ? $result['goal_value'] : 1000; // Meta padrão
    }
    
    public function setUserGoal($userId, $goalValue, $goalType = 'monthly_points') {
        $sql = "INSERT INTO user_goals (user_id, goal_type, goal_value) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                goal_value = VALUES(goal_value), 
                updated_at = CURRENT_TIMESTAMP";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([$userId, $goalType, $goalValue]);
    }
    
    public function __destruct() {
        $this->pdo = null;
    }
}