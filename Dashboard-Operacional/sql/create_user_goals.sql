-- Criar tabela para metas personalizadas dos usuários
USE u406174804_teste;

CREATE TABLE IF NOT EXISTS user_goals (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    goal_type VARCHAR(50) DEFAULT 'monthly_points',
    goal_value DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
    start_date DATE DEFAULT CURRENT_DATE,
    end_date DATE NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_goal (user_id, goal_type, start_date)
);

-- Inserir meta exemplo para o usuário 177362
INSERT INTO user_goals (user_id, goal_type, goal_value) 
VALUES (177362, 'monthly_points', 5427.00)
ON DUPLICATE KEY UPDATE 
goal_value = VALUES(goal_value), 
updated_at = CURRENT_TIMESTAMP;