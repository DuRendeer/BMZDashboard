<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, DELETE');

$response = ['success' => false, 'data' => []];

try {
    $logsFile = '../data/logs.json';
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Limpar logs
        if (file_exists($logsFile)) {
            unlink($logsFile);
        }
        $response['success'] = true;
        $response['message'] = 'Logs cleared successfully';
        
    } else {
        // Buscar logs
        if (file_exists($logsFile)) {
            $logs = json_decode(file_get_contents($logsFile), true) ?: [];
            $response['data'] = $logs;
        }
        $response['success'] = true;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>