<?php
header('Content-Type: application/json');
require_once '../config/database.php';

try {
    $db = new Database();
    $mesas = $db->getAllMesas();
    
    // Converter para formato esperado pelo JavaScript
    $mesasFormatted = [];
    foreach ($mesas as $mesa) {
        $mesasFormatted[$mesa['mesa_id']] = [
            'nome' => $mesa['nome'],
            'funcao' => $mesa['funcao'],
            'status' => $mesa['status'],
            'setor' => $mesa['setor'],
            'turno' => $mesa['turno'],
            'horario_inicio' => $mesa['horario_inicio'],
            'horario_fim' => $mesa['horario_fim'],
            'posicao_x' => (int)$mesa['posicao_x'],
            'posicao_y' => (int)$mesa['posicao_y'],
            'updated_at' => $mesa['updated_at']
        ];
    }
    
    echo json_encode($mesasFormatted);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao carregar dados: ' . $e->getMessage()]);
}
?>