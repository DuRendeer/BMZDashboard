-- ADVBOX Dashboard Database Schema  
-- Execute este script no phpMyAdmin para criar as tabelas
-- O banco u406174804_teste já existe na Hostinger

USE u406174804_teste;

-- Tabela de usuários
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    photo VARCHAR(500),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_email (email)
);

-- Adicionar coluna photo se não existir
ALTER TABLE users ADD COLUMN IF NOT EXISTS photo VARCHAR(500);

-- Tabela de tarefas/tipos de tarefa
CREATE TABLE tasks (
    id INT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    points INT DEFAULT 0,
    category VARCHAR(100),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_points (points)
);

-- Tabela de tarefas concluídas por usuário
CREATE TABLE user_tasks_completed (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_id INT NOT NULL,
    points_earned INT DEFAULT 0,
    completed_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_task_id (task_id),
    INDEX idx_completed_at (completed_at),
    INDEX idx_user_completed (user_id, completed_at)
);

-- Tabela de performance dos usuários (cache calculado)
CREATE TABLE user_performance (
    user_id INT PRIMARY KEY,
    total_points INT DEFAULT 0,
    completed_tasks INT DEFAULT 0,
    current_month_points INT DEFAULT 0,
    current_month_tasks INT DEFAULT 0,
    last_activity DATETIME,
    performance_level ENUM('critical', 'regular', 'good', 'very_good', 'excellent') DEFAULT 'regular',
    goal_progress DECIMAL(5,2) DEFAULT 0.00,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_total_points (total_points),
    INDEX idx_performance_level (performance_level),
    INDEX idx_goal_progress (goal_progress)
);

-- Tabela de configurações e metadados do sistema
CREATE TABLE system_config (
    config_key VARCHAR(100) PRIMARY KEY,
    config_value TEXT,
    description TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Tabela de logs de sincronização
CREATE TABLE sync_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sync_type ENUM('manual', 'automatic', 'scheduled') DEFAULT 'manual',
    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
    records_processed INT DEFAULT 0,
    error_message TEXT,
    started_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    completed_at DATETIME NULL,
    duration_seconds INT DEFAULT 0,
    INDEX idx_sync_type (sync_type),
    INDEX idx_status (status),
    INDEX idx_started_at (started_at)
);

-- Inserir configurações padrão
INSERT INTO system_config (config_key, config_value, description) VALUES 
('monthly_points_goal', '1000', 'Meta mensal de pontos por usuário'),
('monthly_tasks_goal', '50', 'Meta mensal de tarefas por usuário'),
('last_sync', '', 'Timestamp da última sincronização com a API'),
('auto_sync_interval', '30', 'Intervalo de sincronização automática em minutos'),
('auto_sync_enabled', '1', 'Sincronização automática habilitada (1) ou desabilitada (0)'),
('api_key', '', 'Chave da API ADVBOX'),
('dashboard_title', 'ADVBOX Dashboard', 'Título do dashboard'),
('theme_colors', '{"primary": "#3A29FF", "secondary": "#FF94B4", "accent": "#FF3232"}', 'Cores do tema do dashboard');

-- View para facilitar consultas de performance
CREATE VIEW user_performance_summary AS
SELECT 
    u.id,
    u.name,
    u.email,
    up.total_points,
    up.completed_tasks,
    up.current_month_points,
    up.current_month_tasks,
    up.performance_level,
    up.goal_progress,
    up.last_activity,
    CASE 
        WHEN up.goal_progress >= 100 THEN 'Excelente'
        WHEN up.goal_progress >= 75 THEN 'Muito Bom'
        WHEN up.goal_progress >= 50 THEN 'Bom'
        WHEN up.goal_progress >= 25 THEN 'Regular'
        ELSE 'Crítico'
    END as performance_label
FROM users u
LEFT JOIN user_performance up ON u.id = up.user_id
ORDER BY up.total_points DESC;

-- View para estatísticas gerais
CREATE VIEW dashboard_stats AS
SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM tasks) as total_task_types,
    (SELECT SUM(total_points) FROM user_performance) as total_points,
    (SELECT COUNT(*) FROM user_tasks_completed WHERE DATE(completed_at) = CURDATE()) as tasks_today,
    (SELECT COUNT(*) FROM user_performance WHERE goal_progress >= 100) as users_above_goal,
    (SELECT AVG(goal_progress) FROM user_performance) as average_progress;