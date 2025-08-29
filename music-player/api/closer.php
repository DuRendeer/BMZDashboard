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
        $closerId = isset($input['closer']) ? trim($input['closer']) : '';
        $message = isset($input['message']) ? $input['message'] : '';
        
        if (empty($closerId)) {
            throw new Exception('ID do Closer é obrigatório');
        }
        
        // Carregar mapeamento de IDs para nomes
        $mappingFile = '../config/closers_mapping.json';
        if (!file_exists($mappingFile)) {
            throw new Exception('Arquivo de mapeamento não encontrado');
        }
        
        $mapping = json_decode(file_get_contents($mappingFile), true);
        if (!isset($mapping[$closerId])) {
            throw new Exception('ID do Closer não encontrado: ' . $closerId);
        }
        
        $closerName = $mapping[$closerId];
        
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
            'closer_id' => $closerId,
            'closer_name' => $closerName,
            'music_name' => "$closerName - " . pathinfo($randomMusic, PATHINFO_FILENAME),
            'music_url' => $musicPath,
            'message' => "Tocando música do $closerName",
            'timestamp' => time()
        ];
        
        file_put_contents('../data/current_command.json', json_encode($commandData));
        
        // Salvar no log também
        $logEntry = array_merge($commandData, [
            'closer' => $closerName, // Para compatibilidade com o frontend
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