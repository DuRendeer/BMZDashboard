<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$response = ['success' => false, 'data' => null];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $closerName = isset($input['closer_name']) ? trim($input['closer_name']) : '';
        $message = isset($input['message']) ? $input['message'] : '';
        
        if (empty($closerName)) {
            throw new Exception('Nome do Closer é obrigatório');
        }
        
        // Verificar se pasta do closer existe
        $closerDir = '../musica/' . $closerName;
        if (!file_exists($closerDir) || !is_dir($closerDir)) {
            throw new Exception('Closer não encontrado: ' . $closerName);
        }
        
        // Listar arquivos MP3 na pasta do closer
        $musicFiles = [];
        $files = scandir($closerDir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'mp3') {
                $musicFiles[] = $file;
            }
        }
        
        if (empty($musicFiles)) {
            throw new Exception('Nenhuma música encontrada para ' . $closerName);
        }
        
        // Escolher música aleatória
        $randomMusic = $musicFiles[array_rand($musicFiles)];
        $musicPath = 'musica/' . $closerName . '/' . $randomMusic;
        
        // Salvar comando
        $commandData = [
            'closer_name' => $closerName,
            'music_name' => pathinfo($randomMusic, PATHINFO_FILENAME),
            'music_url' => $musicPath,
            'message' => $message ?: "Tocando para $closerName",
            'timestamp' => time()
        ];
        
        file_put_contents('../data/current_command.json', json_encode($commandData));
        
        $response['success'] = true;
        $response['data'] = $commandData;
        $response['message'] = 'Música selecionada: ' . $randomMusic;
        
    } else {
        throw new Exception('Método não permitido');
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
?>