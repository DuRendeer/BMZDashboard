<?php
// Sistema de Mapeamento de Salas - NOVO BI
// Versão 2.0 - Integrado com Banco de Dados
require_once 'config/database.php';

$page = $_GET['page'] ?? 'operacional';
$view = $_GET['view'] ?? '2d';

// Inicializar conexão com banco
try {
    $db = new Database();
    $mesas = $db->getAllMesas();
} catch (Exception $e) {
    $erro_banco = "Erro na conexão: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Mapeamento - NOVO BI</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" type="image/png" href="assets/images/White.png">
</head>
<body>
    <!-- Sidebar com logo e navegação -->
    <div class="sidebar">
        <div class="logo-container">
            <img src="assets/images/White.png" alt="Logo">
            <h2>Mapeamento</h2>
        </div>
        
        <h3 style="margin: 20px 0 15px 0; color: #cccccc; font-size: 16px;">Salas de Trabalho</h3>
        <a href="?page=operacional" class="<?=$page=='operacional'?'active':''?>">
            🏢 Sala do Operacional
        </a>
        <a href="?page=atendimento" class="<?=$page=='atendimento'?'active':''?>">
            📞 Sala Primeiro Atendimento
        </a>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.1);">
            <p style="font-size: 12px; color: #888; text-align: center;">
                Sistema v2.0<br>
                Integrado com BD
            </p>
        </div>
    </div>
    
    <!-- Conteúdo principal -->
    <div class="main-content">
        <?php if (isset($erro_banco)): ?>
            <div class="alert alert-error" style="background: #e74c3c; color: white; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <strong>⚠️ Erro de Conexão:</strong> <?= htmlspecialchars($erro_banco) ?>
                <br><small>Verifique as configurações do banco de dados em config/database.php</small>
            </div>
        <?php endif; ?>
        
        <!-- Controles superiores -->
        <div class="grid-toggle">
            <button class="grid-btn" onclick="toggleGrid()">📊 Grade de Referência</button>
            <button class="export-btn" onclick="exportPositions()">💾 Exportar Posições</button>
            <button class="manage-btn" onclick="location.reload()">🔄 Atualizar Dados</button>
            
            <span style="margin-left: 20px; color: #7f8c8d; font-weight: 500;">
                <?php if($page == 'operacional'): ?>
                    📋 Sala Operacional: 26 mesas configuradas
                <?php else: ?>
                    📋 Sala Atendimento: 20 mesas configuradas  
                <?php endif; ?>
                | 🗄️ Dados em tempo real
            </span>
        </div>
        
        <?php if($page == 'operacional'): ?>
            <h1>🏢 Sala do Operacional</h1>
            <div class="room-container">
                <div class="room-2d operacional-bg">
                    <!-- Grade de referência -->
                    <div class="grid-overlay" id="grid-overlay"></div>
                    
                    <!-- 26 Mesas da Sala Operacional -->
                    <?php for($i = 1; $i <= 26; $i++): ?>
                        <div class="mesa-btn" data-mesa="OP<?=$i?>" onclick="showMesaModal('OP<?=$i?>')">
                            OP<?=$i?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
            
        <?php elseif($page == 'atendimento'): ?>
            <h1>📞 Sala Primeiro Atendimento</h1>
            <div class="room-container">
                <div class="room-2d atendimento-bg">
                    <!-- Grade de referência -->
                    <div class="grid-overlay" id="grid-overlay"></div>
                    
                    <!-- 20 Mesas da Sala Atendimento -->
                    <?php for($i = 1; $i <= 20; $i++): ?>
                        <div class="mesa-btn" data-mesa="AT<?=$i?>" onclick="showMesaModal('AT<?=$i?>')">
                            AT<?=$i?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal para informações e gerenciamento das mesas -->
    <div id="mesaModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle">Mesa X</h2>
                <span class="close" onclick="closeMesaModal()">&times;</span>
            </div>
            <div id="modalContent">
                <!-- Conteúdo será preenchido dinamicamente pelo JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="js/main.js"></script>
    
    <script>
        // Verificar se há erro de banco e desabilitar funcionalidades se necessário
        <?php if (isset($erro_banco)): ?>
            console.error('Erro de banco detectado. Algumas funcionalidades podem não funcionar.');
            
            // Substituir função de carregamento por dados estáticos em caso de erro
            function loadMesasData() {
                console.warn('Usando dados estáticos devido a erro de conexão');
                // Dados de fallback aqui se necessário
            }
        <?php endif; ?>
    </script>
</body>
</html>