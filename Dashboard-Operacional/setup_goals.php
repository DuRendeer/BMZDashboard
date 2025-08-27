<?php
echo "<h1>🎯 Configurar Metas dos Usuários</h1>";
echo "<style>body{background:#000;color:#fff;font-family:Arial;padding:20px;}</style>";

try {
    require_once 'backend/DatabaseManager.php';
    $db = new DatabaseManager();
    
    echo "<h2>1. Criando tabela user_goals...</h2>";
    
    $sql = "CREATE TABLE IF NOT EXISTS user_goals (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        goal_type VARCHAR(50) DEFAULT 'monthly_points',
        goal_value DECIMAL(10,2) NOT NULL DEFAULT 1000.00,
        start_date DATE DEFAULT (CURRENT_DATE),
        end_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        UNIQUE KEY unique_user_goal (user_id, goal_type, start_date)
    )";
    
    $db->pdo->exec($sql);
    echo "✅ Tabela user_goals criada/atualizada!<br>";
    
    echo "<h2>2. Inserindo meta de 5427 pontos para usuário 177362...</h2>";
    
    $stmt = $db->pdo->prepare("INSERT INTO user_goals (user_id, goal_type, goal_value) 
                               VALUES (177362, 'monthly_points', 5427.00)
                               ON DUPLICATE KEY UPDATE 
                               goal_value = VALUES(goal_value), 
                               updated_at = CURRENT_TIMESTAMP");
    $stmt->execute();
    echo "✅ Meta configurada para usuário 177362!<br>";
    
    echo "<h2>3. Configurando metas padrão para outros usuários...</h2>";
    
    // Buscar todos os usuários e configurar metas padrão
    $users = $db->getUsers();
    $metasConfiguradas = 0;
    
    foreach ($users as $user) {
        if ($user['id'] == 177362) continue; // Já configurado
        
        // Meta padrão baseada na performance atual
        $metaPadrao = 1000; // Meta padrão
        
        $stmt = $db->pdo->prepare("INSERT IGNORE INTO user_goals (user_id, goal_type, goal_value) 
                                   VALUES (?, 'monthly_points', ?)");
        if ($stmt->execute([$user['id'], $metaPadrao])) {
            $metasConfiguradas++;
        }
    }
    
    echo "✅ Configuradas metas padrão para {$metasConfiguradas} usuários!<br>";
    
    echo "<h2>4. Verificando metas configuradas:</h2>";
    $stmt = $db->pdo->query("SELECT ug.*, u.name 
                             FROM user_goals ug 
                             JOIN users u ON ug.user_id = u.id 
                             ORDER BY ug.goal_value DESC 
                             LIMIT 10");
    $goals = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse:collapse; width:100%; margin:10px 0;'>";
    echo "<tr><th>Usuário</th><th>Tipo</th><th>Meta</th><th>Data Início</th></tr>";
    foreach ($goals as $goal) {
        echo "<tr>";
        echo "<td>{$goal['name']}</td>";
        echo "<td>{$goal['goal_type']}</td>";
        echo "<td style='color:#00ff00; font-weight:bold;'>{$goal['goal_value']}</td>";
        echo "<td>{$goal['start_date']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2>✅ Setup concluído! Agora o dashboard usará metas personalizadas.</h2>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>❌ Erro: " . $e->getMessage() . "</p>";
}
?>