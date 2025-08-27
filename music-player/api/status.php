<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$response = ['success' => false, 'data' => null];

try {
    $commandFile = '../data/current_command.json';
    
    if (file_exists($commandFile)) {
        $commandData = json_decode(file_get_contents($commandFile), true);
        $response['success'] = true;
        $response['data'] = $commandData;
    } else {
        $response['data'] = [
            'music_id' => null,
            'music_url' => null,
            'music_name' => null,
            'message' => 'Aguardando comando...',
            'timestamp' => null
        ];
        $response['success'] = true;
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(500);
}

echo json_encode($response);
?>