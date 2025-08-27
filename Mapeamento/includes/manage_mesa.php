<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

try {
    $db = new Database();
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update':
            $mesa_id = $_POST['mesa_id'] ?? '';
            $nome = $_POST['nome'] ?? '';
            $funcao = $_POST['funcao'] ?? '';
            $status = $_POST['status'] ?? 'livre';
            $turno = $_POST['turno'] ?? 'Manhã';
            $horario_inicio = $_POST['horario_inicio'] ?? '08:00:00';
            $horario_fim = $_POST['horario_fim'] ?? '17:00:00';
            
            if (empty($mesa_id)) {
                throw new Exception('ID da mesa é obrigatório');
            }
            
            // Se status for livre, limpar nome e função
            if ($status === 'livre') {
                $nome = '';
                $funcao = '';
            }
            
            $dados = [
                'nome' => $nome,
                'funcao' => $funcao,
                'status' => $status,
                'turno' => $turno,
                'horario_inicio' => $horario_inicio,
                'horario_fim' => $horario_fim
            ];
            
            $result = $db->updateMesa($mesa_id, $dados);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Mesa atualizada com sucesso']);
            } else {
                throw new Exception('Erro ao atualizar mesa');
            }
            break;
            
        case 'transfer':
            $mesa_origem = $_POST['mesa_origem'] ?? '';
            $mesa_destino = $_POST['mesa_destino'] ?? '';
            
            if (empty($mesa_origem) || empty($mesa_destino)) {
                throw new Exception('Mesa origem e destino são obrigatórias');
            }
            
            if ($mesa_origem === $mesa_destino) {
                throw new Exception('Mesa origem e destino não podem ser iguais');
            }
            
            $db->transferirFuncionario($mesa_origem, $mesa_destino);
            echo json_encode(['success' => true, 'message' => 'Funcionário transferido com sucesso']);
            break;
            
        case 'remove':
            $mesa_id = $_POST['mesa_id'] ?? '';
            
            if (empty($mesa_id)) {
                throw new Exception('ID da mesa é obrigatório');
            }
            
            $result = $db->removerFuncionario($mesa_id);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Funcionário removido com sucesso']);
            } else {
                throw new Exception('Erro ao remover funcionário');
            }
            break;
            
        default:
            throw new Exception('Ação não reconhecida');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>