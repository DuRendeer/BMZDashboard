<?php
// SYNC ULTRA SIMPLES - só JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Teste simples sem fazer nada
    $result = [
        'success' => true,
        'message' => 'Teste de sincronização funcionou!',
        'records_processed' => 0
    ];
    
    echo json_encode($result);
    exit;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro: ' . $e->getMessage()
    ]);
    exit;
}
?>