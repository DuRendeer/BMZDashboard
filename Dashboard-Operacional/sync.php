<?php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela (quebram JSON)

// Headers primeiro
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache');

// Buffer para capturar qualquer output
ob_start();

try {
    // Agora fazer sincronização REAL
    require_once 'backend/DatabaseManager.php';
    require_once 'backend/AdvboxAPI.php';
    
    $db = new DatabaseManager();
    $api = new AdvboxAPI();
    
    $syncCount = 0;
    $errorCount = 0;
    
    // 1. Puxar e sincronizar usuários
    $users = $api->getUsers();
    foreach ($users as $user) {
        try {
            $userData = [
                'id' => $user['id'],
                'name' => $user['name'] ?? '',
                'email' => $user['email'] ?? '',
                'phone' => $user['phone'] ?? '',
                'photo' => $user['photo'] ?? null
            ];
            
            if ($db->insertOrUpdateUser($userData)) {
                $syncCount++;
            }
        } catch (Exception $e) {
            $errorCount++;
        }
    }
    
    // 2. Puxar e sincronizar performance
    $topPerformers = $api->getTopPerformers(50);
    foreach ($topPerformers as $performer) {
        try {
            $performanceData = [
                'user_id' => $performer['id'],
                'total_points' => $performer['total_points'] ?? 0,
                'completed_tasks' => $performer['completed_tasks'] ?? 0,
                'current_month_points' => $performer['current_month_points'] ?? 0,
                'current_month_tasks' => $performer['completed_tasks'] ?? 0,
                'last_activity' => date('Y-m-d H:i:s'),
                'performance_level' => $performer['performance_level'] ?? 'critical',
                'goal_progress' => $performer['goal_progress'] ?? 0
            ];
            
            $db->updateUserPerformance($performer['id'], $performanceData);
        } catch (Exception $e) {
            $errorCount++;
        }
    }
    
    // 3. Atualizar última sincronização
    $db->setConfig('last_sync', date('Y-m-d H:i:s'));
    
    $result = [
        'success' => true,
        'message' => "Sincronização concluída! {$syncCount} registros atualizados",
        'details' => [
            'users_synced' => count($users),
            'performers_synced' => count($topPerformers),
            'records_processed' => $syncCount,
            'errors' => $errorCount,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ];
    
    // Limpar buffer e enviar JSON
    ob_clean();
    echo json_encode($result, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    // Limpar buffer em caso de erro
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
    
} catch (Error $e) {
    // Capturar erros fatais também
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Fatal Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ], JSON_PRETTY_PRINT);
}

// Finalizar buffer
ob_end_flush();
exit;
?>