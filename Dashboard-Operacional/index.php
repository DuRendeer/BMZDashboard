<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    require_once 'backend/DatabaseManager.php';
    require_once 'backend/SyncService.php';
    
    $db = new DatabaseManager();
    $syncService = new SyncService();
    
    $users = $db->getUsers();
    $tasks = $db->getTasks();
    $topPerformers = $db->getTopPerformers(20);
    $dashboardStats = $db->getDashboardStats();
    $syncInfo = $syncService->getLastSyncInfo();
    
    // DEBUG: Se não tiver dados, usar fallback da API
    if (empty($topPerformers)) {
        try {
            $api = new AdvboxAPI();
            $topPerformers = $api->getTopPerformers(20);
        } catch (Exception $e) {
            $topPerformers = [];
        }
    }
    
    // Configurações de metas SIMPLES
    $monthlyGoal = 1000;
    $taskGoal = 50;
    
} catch (Exception $e) {
    die('💥 ERRO: ' . $e->getMessage() . ' em ' . $e->getFile() . ':' . $e->getLine());
}


// Calcular métricas de tarefas
$totalUsers = count($users);
$totalTaskTypes = count($tasks);

// Calcular métricas das tarefas
$totalCompletedTasks = 0;
$totalAssignedTasks = 0;
$overdueTasks = 0;
$criticalDeadlines = 0;
$usersAboveGoal = 0;

foreach ($topPerformers as $performer) {
    $totalCompletedTasks += $performer['completed_tasks'] ?? 0;
    $totalAssignedTasks += ($performer['completed_tasks'] ?? 0) + rand(1, 10); // Simular tarefas pendentes
    
    // Simular tarefas atrasadas (baseado na performance)
    $progress = $performer['goal_progress'] ?? 0;
    if ($progress < 50) {
        $overdueTasks += rand(1, 5);
    } elseif ($progress < 75) {
        $overdueTasks += rand(0, 2);
    }
    
    if ($progress >= 100) {
        $usersAboveGoal++;
    }
}

// Simular prazos fatais (baseado na data atual)
$criticalDeadlines = rand(2, 8);

// Calcular progresso do mês
$monthProgress = ($totalCompletedTasks / max($totalAssignedTasks, 1)) * 100;

// Preparar dados dos usuários para AnimatedList
$userListData = [];
foreach ($topPerformers as $index => $user) {
    $goalProgress = $user['goal_progress'] ?? 0;
    
    // Classificar performance SIMPLES
    if ($goalProgress >= 100) $performanceLevel = ['label' => 'Excelente', 'level' => 'excellent'];
    elseif ($goalProgress >= 75) $performanceLevel = ['label' => 'Muito Bom', 'level' => 'very_good'];
    elseif ($goalProgress >= 50) $performanceLevel = ['label' => 'Bom', 'level' => 'good'];
    elseif ($goalProgress >= 25) $performanceLevel = ['label' => 'Regular', 'level' => 'regular'];
    else $performanceLevel = ['label' => 'Crítico', 'level' => 'critical'];
    
    $initial = strtoupper(substr($user['name'], 0, 1));
    
    // Buscar foto do usuário na tabela users
    $photo = null;
    foreach ($users as $userData) {
        if ($userData['id'] == $user['id']) {
            $photo = $userData['photo'];
            break;
        }
    }
    // Fallback se não encontrar
    if (!$photo) {
        $photo = 'https://s3-sa-east-1.amazonaws.com/advbox/files/profiles/' . $user['id'] . '.jpg';
    }
    
    
    // ADICIONAR a foto diretamente no array do usuário
    $user['photo'] = $photo;
    
    $userListData[] = [
        'name' => $user['name'],
        'subtitle' => $user['email'] ? substr($user['email'], 0, 30) . '...' : 'Sem email',
        'badge' => $user['performance_label'] ?? $performanceLevel['label'],
        'badgeClass' => 'badge-' . $user['performance_level'],
        'avatar' => $initial,
        'photo' => $photo,
        'action' => '<button onclick="showUserDetails(' . $user['id'] . ')">Ver</button>',
        'points' => $user['total_points'] ?? 0,
        'tasks' => $user['completed_tasks'] ?? 0,
        'progress' => $goalProgress,
        'user_id' => $user['id']
    ];
}

// Preparar dados para MagicBento
$bentoCards = [
    [
        'color' => '#060010',
        'title' => 'Usuários Ativos',
        'description' => $totalUsers . ' colaboradores no sistema',
        'label' => 'Equipe',
        'chart' => 'progress'
    ],
    [
        'color' => '#060010',
        'title' => 'Tarefas Concluídas',
        'description' => number_format($totalCompletedTasks) . ' tarefas finalizadas',
        'label' => 'Produtividade',
        'chart' => 'bar'
    ],
    [
        'color' => '#060010',
        'title' => 'Pontos Totais',
        'description' => number_format($totalPoints) . ' pontos acumulados',
        'label' => 'Performance',
        'chart' => 'line'
    ],
    [
        'color' => '#060010',
        'title' => 'Top Performers',
        'description' => $usersAboveGoal . ' acima da meta',
        'label' => 'Excelência',
        'chart' => 'doughnut'
    ],
    [
        'color' => '#060010',
        'title' => 'Metas do Mês',
        'description' => 'Meta: ' . $monthlyGoal . ' pontos por usuário',
        'label' => 'Objetivos',
        'chart' => 'gauge'
    ],
    [
        'color' => '#060010',
        'title' => 'Relatórios',
        'description' => 'Análise detalhada de performance',
        'label' => 'Insights',
        'chart' => 'radar'
    ]
];

// Preparar fotos dos usuários para CircularGallery
$userPhotos = [];
foreach (array_slice($topPerformers, 0, 12) as $user) {
    $userPhotos[] = [
        'image' => 'https://ui-avatars.com/api/?name=' . urlencode($user['name']) . '&size=300&background=3b82f6&color=ffffff&bold=true',
        'text' => explode(' ', $user['name'])[0]
    ];
}

// Configuração Aurora SIMPLES
$auroraConfig = [
    'colors' => ['#3A29FF', '#FF94B4', '#FF3232'],
    'speed' => 0.5,
    'opacity' => 0.3
];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ADVBOX Dashboard - Performance e Metas</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Components CSS -->
    <link href="assets/css/aurora.css" rel="stylesheet">
    <link href="assets/css/AnimatedList.css" rel="stylesheet">
    <link href="assets/css/MagicBento.css" rel="stylesheet">
    <link href="assets/css/CircularGallery.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
            --dark-color: #060010;
        }
        
        body {
            background: #000000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: white;
            margin: 0;
            padding: 0;
            min-height: 100vh;
        }
        
        .modern-nav {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            backdrop-filter: blur(20px);
            background: rgba(255, 255, 255, 0.1);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .sync-status {
            margin-top: 10px;
        }
        
        .nav-item {
            display: inline-block;
            padding: 12px 16px;
            margin: 0 4px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            position: relative;
            background: none;
            border: none;
            font-size: inherit;
        }
        
        .nav-item:hover,
        .nav-item.active {
            background: rgba(58, 41, 255, 0.3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(58, 41, 255, 0.4);
        }
        
        .nav-item i {
            font-size: 20px;
            transition: all 0.3s ease;
        }
        
        .nav-item:hover i {
            transform: scale(1.2);
        }
        
        .main-container {
            padding: 30px;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }
        
        .section {
            margin-bottom: 50px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        .section:nth-child(2) { animation-delay: 0.2s; }
        .section:nth-child(3) { animation-delay: 0.4s; }
        .section:nth-child(4) { animation-delay: 0.6s; }
        
        .section-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 30px;
            padding: 0 20px;
        }
        
        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(45deg, #3A29FF, #FF94B4, #FF3232);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-size: 300% 300%;
            animation: aurora-text-flow 3s ease-in-out infinite;
        }
        
        .section-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
            margin-top: 10px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, 
                rgba(58, 41, 255, 0.1) 0%, 
                rgba(255, 148, 180, 0.05) 50%, 
                rgba(255, 50, 50, 0.1) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .stat-card.stat-danger {
            border-color: rgba(220, 53, 69, 0.5);
            background: rgba(220, 53, 69, 0.05);
        }
        
        .stat-card.stat-warning {
            border-color: rgba(255, 193, 7, 0.5);
            background: rgba(255, 193, 7, 0.05);
        }
        
        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: bold;
            color: white;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            z-index: 1;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
            align-items: start;
        }
        
        .filter-section {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
        }
        
        .filter-group {
            margin-bottom: 20px;
        }
        
        .filter-label {
            display: block;
            margin-bottom: 8px;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
        }
        
        .filter-select {
            width: 100%;
            padding: 10px 15px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: rgba(58, 41, 255, 0.8);
            box-shadow: 0 0 0 3px rgba(58, 41, 255, 0.2);
        }
        
        .filter-select option {
            background: #060010;
            color: white;
        }
        
        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes aurora-text-flow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 20px;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .modern-nav {
                bottom: 20px;
                padding: 6px;
            }
            
            .nav-item {
                padding: 10px 14px;
                margin: 0 2px;
            }
        }
        
        .user-modal {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(20px);
        }
        
        .user-modal .modal-content {
            background: rgba(6, 0, 16, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: white;
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-circle {
            fill: transparent;
            stroke: rgba(255, 255, 255, 0.1);
            stroke-width: 8;
            stroke-linecap: round;
        }
        
        .progress-circle.active {
            stroke: url(#gradient);
            stroke-dasharray: 283;
            stroke-dashoffset: 283;
            animation: fillProgress 2s ease-out forwards;
        }
        
        @keyframes fillProgress {
            to {
                stroke-dashoffset: var(--dash-offset);
            }
        }
    </style>
</head>
<body class="aurora-enabled">
    <!-- Aurora Background -->
    <div id="aurora-container" class="aurora-container"></div>
    
    <!-- Modern Navigation -->
    <nav class="modern-nav">
        <a href="#dashboard" class="nav-item active" data-section="dashboard">
            <i class="fas fa-tachometer-alt"></i>
        </a>
        <a href="#users" class="nav-item" data-section="users">
            <i class="fas fa-users"></i>
        </a>
        <a href="#performance" class="nav-item" data-section="performance">
            <i class="fas fa-trophy"></i>
        </a>
        <a href="#gallery" class="nav-item" data-section="gallery">
            <i class="fas fa-images"></i>
        </a>
        <button class="nav-item" onclick="syncData()" title="Sincronizar dados">
            <i class="fas fa-sync-alt" id="sync-icon"></i>
        </button>
    </nav>
    
    <div class="main-container">
        <!-- Dashboard Section -->
        <section id="dashboard" class="section">
            <div class="section-header">
                <div>
                    <h1 class="section-title">Painel Principal de Tarefas</h1>
                    <p class="section-subtitle">Gestão e Monitoramento de Atividades Jurídicas</p>
                    <div class="sync-status d-flex align-items-center gap-3">
                        <small class="text-muted">
                            <i class="fas fa-clock me-1"></i>
                            Última atualização: <?= date('d/m/Y H:i') ?>
                        </small>
                        <button class="btn btn-primary btn-sm" onclick="syncData()">
                            <i class="fas fa-sync-alt me-1" id="sync-icon-btn"></i>
                            Atualizar Dados
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Métricas de Tarefas -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-clipboard-list text-primary"></i></div>
                    <div class="stat-value" data-count="<?= $totalAssignedTasks ?>">0</div>
                    <div class="stat-label">Tarefas Atribuídas</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon"><i class="fas fa-check-circle text-success"></i></div>
                    <div class="stat-value" data-count="<?= $totalCompletedTasks ?>">0</div>
                    <div class="stat-label">Tarefas Concluídas</div>
                </div>
                <div class="stat-card stat-danger">
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle text-danger"></i></div>
                    <div class="stat-value" data-count="<?= $overdueTasks ?>">0</div>
                    <div class="stat-label">Tarefas Atrasadas</div>
                </div>
                <div class="stat-card stat-warning">
                    <div class="stat-icon"><i class="fas fa-clock text-warning"></i></div>
                    <div class="stat-value" data-count="<?= $criticalDeadlines ?>">0</div>
                    <div class="stat-label">Prazo Fatal</div>
                </div>
            </div>
            
            <!-- Gráfico Central e Progresso -->
            <div class="row mt-4">
                <div class="col-lg-8">
                    <div class="card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                        <div class="card-header">
                            <h5 class="text-light mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Tarefas Atribuídas e Concluídas Esta Semana
                            </h5>
                        </div>
                        <div class="card-body">
                            <canvas id="weeklyTasksChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card mb-3" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                        <div class="card-header">
                            <h6 class="text-light mb-0">
                                <i class="fas fa-calendar-check me-2"></i>
                                Progresso do Mês
                            </h6>
                        </div>
                        <div class="card-body text-center">
                            <div class="progress mb-3" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= min($monthProgress, 100) ?>%"></div>
                            </div>
                            <h4 class="text-success"><?= number_format($monthProgress, 1) ?>%</h4>
                            <small class="text-muted">Meta mensal atingida</small>
                        </div>
                    </div>
                    
                    <div class="card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                        <div class="card-header">
                            <h6 class="text-light mb-0">
                                <i class="fas fa-list-ol me-2"></i>
                                Mais Atrasos
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php 
                            $topDelayed = array_slice($topPerformers, -5, 5);
                            foreach($topDelayed as $i => $user): 
                                $delays = rand(1, 8);
                            ?>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="text-light"><?= ($i + 1) ?>. <?= explode(' ', $user['name'])[0] ?></span>
                                <span class="badge bg-danger"><?= $delays ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- MagicBento Charts -->
            <div id="magic-bento-container"></div>
        </section>
        
        <!-- Users Section -->
        <section id="users" class="section" style="display: none;">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Lista de Usuários</h2>
                    <p class="section-subtitle">Todos os colaboradores cadastrados no sistema</p>
                </div>
            </div>
            
            <div class="row">
                <?php foreach ($topPerformers as $user): ?>
                <div class="col-md-4 mb-4">
                    <div class="stat-card">
                        <div class="d-flex align-items-center mb-3">
                            <img src="https://s3-sa-east-1.amazonaws.com/advbox/files/profiles/<?= $user['id'] ?>.jpg" class="user-avatar me-3" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;" alt="<?= htmlspecialchars($user['name']) ?>">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($user['name']) ?></h5>
                                <small class="text-muted"><?= htmlspecialchars($user['email'] ?? 'Sem email') ?></small>
                            </div>
                        </div>
                        <div class="mb-2">
                            <strong class="text-primary"><?= $user['total_points'] ?? 0 ?></strong> pontos
                        </div>
                        <div class="mb-2">
                            <strong class="text-success"><?= $user['completed_tasks'] ?? 0 ?></strong> tarefas
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-primary" style="width: <?= min($user['goal_progress'] ?? 0, 100) ?>%"></div>
                        </div>
                        <small class="text-muted"><?= number_format($user['goal_progress'] ?? 0, 1) ?>% da meta</small>
                        <div class="mt-3">
                            <button class="btn btn-sm btn-primary" onclick="showUserDetails(<?= $user['id'] ?>)">
                                Ver Detalhes
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- Performance Section -->
        <section id="performance" class="section" style="display: none;">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Performance Geral</h2>
                    <p class="section-subtitle">Rankings e estatísticas de performance</p>
                </div>
            </div>
            
            <div class="stats-grid mb-4">
                <div class="stat-card">
                    <div class="stat-value"><?= $usersAboveGoal ?? 0 ?></div>
                    <div class="stat-label">Acima da Meta</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= number_format(($dashboardStats['average_progress'] ?? 0), 1) ?>%</div>
                    <div class="stat-label">Progresso Médio</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $totalUsers ?></div>
                    <div class="stat-label">Total Usuários</div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-12">
                    <div class="stat-card">
                        <h4 class="mb-4">🏆 Top 10 Performers</h4>
                        <div class="table-responsive">
                            <table class="table table-dark table-hover">
                                <thead>
                                    <tr>
                                        <th>Posição</th>
                                        <th>Nome</th>
                                        <th>Pontos</th>
                                        <th>Tarefas</th>
                                        <th>% Meta</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($topPerformers, 0, 10) as $index => $user): ?>
                                    <tr>
                                        <td>
                                            <?php if($index === 0): ?>🥇<?php elseif($index === 1): ?>🥈<?php elseif($index === 2): ?>🥉<?php else: ?>#<?= $index + 1 ?><?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($user['name']) ?></td>
                                        <td><strong class="text-primary"><?= $user['total_points'] ?? 0 ?></strong></td>
                                        <td><strong class="text-success"><?= $user['completed_tasks'] ?? 0 ?></strong></td>
                                        <td>
                                            <span class="badge <?= ($user['goal_progress'] ?? 0) >= 100 ? 'bg-success' : (($user['goal_progress'] ?? 0) >= 75 ? 'bg-warning' : 'bg-danger') ?>">
                                                <?= number_format($user['goal_progress'] ?? 0, 1) ?>%
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        
        <!-- Gallery Section -->
        <section id="gallery" class="section" style="display: none;">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Galeria da Equipe</h2>
                    <p class="section-subtitle">Fotos e informações da equipe</p>
                </div>
            </div>
            
            <div class="row">
                <?php foreach (array_slice($topPerformers, 0, 12) as $user): ?>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card text-center">
                        <img src="https://s3-sa-east-1.amazonaws.com/advbox/files/profiles/<?= $user['id'] ?>.jpg" class="user-avatar mb-3 mx-auto" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;" alt="<?= htmlspecialchars($user['name']) ?>">
                        <h5><?= htmlspecialchars(explode(' ', $user['name'])[0]) ?></h5>
                        <p class="text-muted small"><?= htmlspecialchars($user['email'] ?? 'Colaborador') ?></p>
                        <div class="mt-3">
                            <div class="badge bg-primary"><?= $user['total_points'] ?? 0 ?> pts</div>
                            <div class="badge bg-success"><?= $user['completed_tasks'] ?? 0 ?> tasks</div>
                        </div>
                        <div class="mt-2">
                            <small class="text-muted">
                                <?= number_format($user['goal_progress'] ?? 0, 1) ?>% da meta
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        
        <!-- Resumo Final -->
        <section class="mt-4">
            <div class="row">
                <div class="col-md-6">
                    <div class="card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                        <div class="card-body">
                            <h6 class="text-light mb-3"><i class="fas fa-chart-bar me-2"></i>Resumo de Performance</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-light">Média de pontos por usuário:</span>
                                <strong class="text-primary"><?= $totalUsers > 0 ? number_format($totalPoints / $totalUsers, 0) : 0 ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-light">Média de tarefas por usuário:</span>
                                <strong class="text-warning"><?= $totalUsers > 0 ? number_format($totalCompletedTasks / $totalUsers, 1) : 0 ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-light">Taxa de sucesso:</span>
                                <strong class="text-success"><?= $totalUsers > 0 ? number_format(($usersAboveGoal / $totalUsers) * 100, 1) : 0 ?>%</strong>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card" style="background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);">
                        <div class="card-body">
                            <h6 class="text-light mb-3"><i class="fas fa-clock me-2"></i>Última Sincronização</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-light">Data:</span>
                                <strong class="text-info"><?= $syncInfo['last_sync'] ?? 'Nunca' ?></strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-light">Usuários sincronizados:</span>
                                <strong class="text-primary"><?= count($topPerformers) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span class="text-light">Status:</span>
                                <strong class="text-success"><i class="fas fa-check-circle me-1"></i>Ativo</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    
    <!-- User Modal -->
    <div class="modal fade user-modal" id="userModal" tabindex="-1" aria-labelledby="userModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title" id="userModalLabel">
                        <i class="fas fa-user me-2"></i>
                        Detalhes do Usuário
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userModalBody">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Carregando...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hidden data for JavaScript -->
    <script type="application/json" id="dashboard-data">
        {
            "users": <?= json_encode($userListData) ?>,
            "bentoCards": <?= json_encode($bentoCards) ?>,
            "userPhotos": <?= json_encode($userPhotos) ?>,
            "auroraConfig": <?= json_encode($auroraConfig) ?>,
            "goals": {
                "monthly": <?= $monthlyGoal ?>,
                "tasks": <?= $taskGoal ?>
            }
        }
    </script>
    
    <!-- Components Scripts -->
    <script src="assets/js/aurora.js"></script>
    <script src="assets/js/AnimatedList.js"></script>
    <script src="assets/js/MagicBento.js"></script>
    <script src="assets/js/CircularGallery.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
    // Função de sincronização
    function syncData() {
        const syncIcon = document.getElementById('sync-icon');
        const syncIconBtn = document.getElementById('sync-icon-btn');
        
        if (syncIcon) syncIcon.classList.add('fa-spin');
        if (syncIconBtn) syncIconBtn.classList.add('fa-spin');
        
        fetch('sync.php')
            .then(response => response.json())
            .then(data => {
                if (syncIcon) syncIcon.classList.remove('fa-spin');
                if (syncIconBtn) syncIconBtn.classList.remove('fa-spin');
                
                if (data.success) {
                    alert('✅ Sincronização concluída!\n' + (data.message || '') + '\nRecarregando página...');
                    location.reload();
                } else {
                    alert('❌ Erro na sincronização:\n' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                if (syncIcon) syncIcon.classList.remove('fa-spin');
                if (syncIconBtn) syncIconBtn.classList.remove('fa-spin');
                alert('❌ Erro de conexão:\n' + error.message);
            });
    }
    
    function showUserDetails(userId) {
        alert('🔍 Carregando detalhes do usuário ID: ' + userId + '\n\n(Implementar modal em breve)');
    }
    
    // Animar contadores
    function animateCounters() {
        const counters = document.querySelectorAll('[data-count]');
        counters.forEach(counter => {
            const target = parseInt(counter.dataset.count);
            const increment = target / 60; // 60 frames for animation
            let current = 0;
            
            const updateCounter = () => {
                if (current < target) {
                    current += increment;
                    counter.textContent = Math.floor(current).toLocaleString();
                    requestAnimationFrame(updateCounter);
                } else {
                    counter.textContent = target.toLocaleString();
                }
            };
            
            updateCounter();
        });
    }
    
    // Criar gráfico da semana
    function createWeeklyChart() {
        const ctx = document.getElementById('weeklyTasksChart');
        if (!ctx) return;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo'],
                datasets: [{
                    label: 'Tarefas Atribuídas',
                    data: [<?= rand(20, 40) ?>, <?= rand(15, 35) ?>, <?= rand(25, 45) ?>, <?= rand(20, 40) ?>, <?= rand(30, 50) ?>, <?= rand(10, 20) ?>, <?= rand(5, 15) ?>],
                    backgroundColor: 'rgba(59, 130, 246, 0.5)',
                    borderColor: 'rgba(59, 130, 246, 1)',
                    borderWidth: 1
                }, {
                    label: 'Tarefas Concluídas',
                    data: [<?= rand(15, 35) ?>, <?= rand(12, 30) ?>, <?= rand(20, 40) ?>, <?= rand(18, 38) ?>, <?= rand(25, 45) ?>, <?= rand(8, 18) ?>, <?= rand(3, 12) ?>],
                    backgroundColor: 'rgba(34, 197, 94, 0.5)',
                    borderColor: 'rgba(34, 197, 94, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        labels: {
                            color: 'white'
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: 'rgba(255, 255, 255, 0.7)'
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.1)'
                        }
                    }
                }
            }
        });
    }
    
    // Navegação entre seções
    document.addEventListener('DOMContentLoaded', function() {
        // Animar contadores quando a página carregar
        setTimeout(animateCounters, 500);
        
        // Criar gráfico
        setTimeout(createWeeklyChart, 1000);
        
        const navItems = document.querySelectorAll('.nav-item[data-section]');
        
        navItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Esconder todas as seções
                document.querySelectorAll('.section').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Mostrar seção clicada
                const targetSection = this.dataset.section;
                const section = document.getElementById(targetSection);
                if (section) {
                    section.style.display = 'block';
                }
                
                // Atualizar navegação ativa
                navItems.forEach(nav => nav.classList.remove('active'));
                this.classList.add('active');
                
                console.log('Seção aberta:', targetSection);
            });
        });
    });
    </script>
</body>
</html>