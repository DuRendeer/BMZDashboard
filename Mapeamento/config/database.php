<?php
// Configurações do banco de dados - Hostinger
define('DB_HOST', '127.0.0.1:3306');
define('DB_NAME', 'u406174804_BANCO_BMZ');
define('DB_USER', '');
define('DB_PASS', ''); // Substitua pela senha real do banco

class Database {
    private $connection;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Erro na conexão: " . $e->getMessage());
        }
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Buscar todos os funcionários/mesas
    public function getAllMesas() {
        $sql = "SELECT * FROM funcionarios_mesas ORDER BY mesa_id";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // Buscar mesa específica
    public function getMesa($mesa_id) {
        $sql = "SELECT * FROM funcionarios_mesas WHERE mesa_id = ?";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$mesa_id]);
        return $stmt->fetch();
    }
    
    // Atualizar dados da mesa
    public function updateMesa($mesa_id, $dados) {
        $sql = "UPDATE funcionarios_mesas SET 
                nome = ?, funcao = ?, status = ?, turno = ?, 
                horario_inicio = ?, horario_fim = ?,
                updated_at = CURRENT_TIMESTAMP 
                WHERE mesa_id = ?";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            $dados['nome'], 
            $dados['funcao'], 
            $dados['status'], 
            $dados['turno'],
            $dados['horario_inicio'] ?? '08:00:00',
            $dados['horario_fim'] ?? '17:00:00',
            $mesa_id
        ]);
    }
    
    // Transferir funcionário entre mesas
    public function transferirFuncionario($mesa_origem, $mesa_destino) {
        try {
            $this->connection->beginTransaction();
            
            // Buscar dados da mesa origem
            $funcionario = $this->getMesa($mesa_origem);
            if (!$funcionario || $funcionario['status'] !== 'ocupada') {
                throw new Exception("Mesa origem não está ocupada");
            }
            
            // Verificar se mesa destino está livre
            $mesa_dest = $this->getMesa($mesa_destino);
            if (!$mesa_dest || $mesa_dest['status'] !== 'livre') {
                throw new Exception("Mesa destino não está disponível");
            }
            
            // Transferir dados
            $this->updateMesa($mesa_destino, [
                'nome' => $funcionario['nome'],
                'funcao' => $funcionario['funcao'],
                'status' => 'ocupada',
                'turno' => $funcionario['turno'],
                'horario_inicio' => $funcionario['horario_inicio'],
                'horario_fim' => $funcionario['horario_fim']
            ]);
            
            // Limpar mesa origem
            $this->updateMesa($mesa_origem, [
                'nome' => '',
                'funcao' => '',
                'status' => 'livre',
                'turno' => $funcionario['turno'],
                'horario_inicio' => $funcionario['horario_inicio'],
                'horario_fim' => $funcionario['horario_fim']
            ]);
            
            $this->connection->commit();
            return true;
            
        } catch (Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }
    
    // Remover funcionário da mesa
    public function removerFuncionario($mesa_id) {
        return $this->updateMesa($mesa_id, [
            'nome' => '',
            'funcao' => '',
            'status' => 'livre',
            'turno' => 'Manhã',
            'horario_inicio' => '08:00:00',
            'horario_fim' => '17:00:00'
        ]);
    }
}
?>