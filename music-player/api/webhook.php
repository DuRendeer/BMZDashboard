<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'message' => '', 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            throw new Exception('Invalid JSON input');
        }
        
        $closerName = isset($input['closer']) ? $input['closer'] : null;
        $message = isset($input['message']) ? $input['message'] : '';
        
        if (!$closerName) {
            throw new Exception('closer is required');
        }
        
        $musicsConfig = json_decode(file_get_contents('../config/musics.json'), true);
        
        if (!isset($musicsConfig['closers'][$closerName])) {
            throw new Exception('Closer not found');
        }
        
        $closerData = $musicsConfig['closers'][$closerName];
        $folder = $closerData['folder'];
        
        // Buscar arquivos 1.mp3, 2.mp3, 3.mp3 na pasta
        $musicFiles = [];
        for ($i = 1; $i <= 3; $i++) {
            $filePath = "../{$folder}/{$i}.mp3";
            if (file_exists($filePath)) {
                $musicFiles[] = "{$folder}/{$i}.mp3";
            }
        }
        
        if (empty($musicFiles)) {
            throw new Exception('No music files found for this closer');
        }
        
        // Selecionar música aleatoriamente
        $randomIndex = rand(0, count($musicFiles) - 1);
        $selectedMusicUrl = $musicFiles[$randomIndex];
        $musicNumber = $randomIndex + 1;
        
        $commandData = [
            'closer' => $closerName,
            'music_url' => $selectedMusicUrl,
            'music_name' => "{$closerName} - Música {$musicNumber}",
            'music_number' => $musicNumber,
            'message' => $message,
            'timestamp' => time()
        ];
        
        file_put_contents('../data/current_command.json', json_encode($commandData));
        
        // Salvar no log
        $logEntry = array_merge($commandData, [
            'date' => date('d/m/Y H:i:s', $commandData['timestamp'])
        ]);
        
        $logsFile = '../data/logs.json';
        $logs = [];
        if (file_exists($logsFile)) {
            $logs = json_decode(file_get_contents($logsFile), true) ?: [];
        }
        
        array_unshift($logs, $logEntry); // Adicionar no início
        $logs = array_slice($logs, 0, 100); // Manter apenas os últimos 100
        
        file_put_contents($logsFile, json_encode($logs));
        
        $response['success'] = true;
        $response['message'] = 'Command received successfully';
        $response['data'] = $commandData;
        
    } else {
        throw new Exception('Only POST method is allowed');
    }
    
} catch (Exception $e) {
    $response['message'] = $e->getMessage();
    http_response_code(400);
}

echo json_encode($response);
?>