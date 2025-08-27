<?php
require_once 'backend/AdvboxAPI.php';

$api = new AdvboxAPI();

try {
    $users = $api->getUsers();
    $tasks = $api->getTasks();
    $recentTasks = $api->getTasksCompleted(date('Y-m-01'), date('Y-m-d'), 1000);
    $topPerformers = $api->getTopPerformers(20);
} catch (Exception $e) {
    die('Erro ao carregar dados: ' . $e->getMessage());
}

// Análise de metas e performance
$monthlyGoal = 500; // Meta mensal de pontos por usuário
$taskGoal = 20; // Meta mensal de tarefas por usuário

// Calcular estatísticas do mês
$monthStats = [];
$totalMonthlyPoints = 0;
$totalMonthlyTasks = 0;

foreach ($topPerformers as $performer) {
    $user = $performer['user'];
    $performance = $performer['performance'];
    
    $goalProgress = ($performance['total_points'] / $monthlyGoal) * 100;
    $taskProgress = ($performance['completed_tasks'] / $taskGoal) * 100;
    
    $monthStats[] = [
        'user' => $user,
        'performance' => $performance,
        'goal_progress' => min($goalProgress, 100),
        'task_progress' => min($taskProgress, 100),
        'points_remaining' => max(0, $monthlyGoal - $performance['total_points']),
        'tasks_remaining' => max(0, $taskGoal - $performance['completed_tasks'])
    ];
    
    $totalMonthlyPoints += $performance['total_points'];
    $totalMonthlyTasks += $performance['completed_tasks'];
}

// Análise por tipo de tarefa
$taskAnalysis = [];
foreach ($tasks as $task) {
    $taskAnalysis[] = [
        'task' => $task['task'],
        'reward' => $task['reward'],
        'id' => $task['id']
    ];
}

// Ordenar por recompensa
usort($taskAnalysis, function($a, $b) {
    return $b['reward'] <=> $a['reward'];
});

$currentMonth = date('F Y');
$daysInMonth = date('t');
$currentDay = date('j');
$monthProgress = ($currentDay / $daysInMonth) * 100;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios e Metas - ADVBOX Dashboard</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Aurora CSS -->
    <link href="aurora.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --success-color: #10b981;
            --warning-color: #f59e0b;
            --danger-color: #ef4444;
        }
        
        body {
            background-color: #f8fafc;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .goal-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .goal-card:hover {
            transform: translateY(-3px);
        }
        
        .progress-ring {
            width: 120px;
            height: 120px;
        }
        
        .progress-ring circle {
            fill: transparent;
            stroke-width: 8;
            stroke-linecap: round;
        }
        
        .progress-bg {
            stroke: #e5e7eb;
        }
        
        .progress-bar-svg {
            stroke: var(--secondary-color);
            stroke-dasharray: 339; /* 2 * π * 54 */
            stroke-dashoffset: 339;
            transition: stroke-dashoffset 1s ease-in-out;
        }
        
        .user-goal-row {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .user-goal-row:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.12);
        }
        
        .mini-chart {
            width: 60px;
            height: 40px;
        }
        
        .status-excellent { color: var(--success-color); }
        .status-good { color: var(--primary-color); }
        .status-warning { color: var(--warning-color); }
        .status-danger { color: var(--danger-color); }
        
        .task-value-high { background: linear-gradient(135deg, #10b981, #059669); }
        .task-value-medium { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .task-value-low { background: linear-gradient(135deg, #6b7280, #4b5563); }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 1rem 0;
        }
        
        .navbar-brand {
            color: white !important;
            font-weight: bold;
        }
        
        .nav-link-custom {
            color: rgba(255,255,255,0.8) !important;
            transition: color 0.3s ease;
        }
        
        .nav-link-custom:hover {
            color: white !important;
        }
    </style>
</head>
<body class="aurora-enabled">
    <!-- Aurora Background -->
    <div id="aurora-container" class="aurora-container"></div>
    
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-chart-line me-2"></i>
                ADVBOX Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link nav-link-custom" href="index.php">
                    <i class="fas fa-home me-1"></i>
                    Dashboard
                </a>
                <a class="nav-link nav-link-custom active" href="reports.php">
                    <i class="fas fa-chart-bar me-1"></i>
                    Relatórios
                </a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-1">📊 Relatórios e Metas</h1>
                        <p class="text-muted">Análise detalhada de performance - <?= $currentMonth ?></p>
                    </div>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="small text-muted">Progresso do Mês</div>
                            <div class="progress" style="width: 150px; height: 8px;">
                                <div class="progress-bar bg-primary" style="width: <?= $monthProgress ?>%"></div>
                            </div>
                            <div class="small text-muted"><?= $currentDay ?>/<?= $daysInMonth ?> dias</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Resumo Geral de Metas -->
        <div class="row mb-4">
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="goal-card text-center">
                    <div class="mb-3">
                        <svg class="progress-ring" viewBox="0 0 120 120">
                            <circle class="progress-bg" cx="60" cy="60" r="54"></circle>
                            <circle class="progress-bar-svg" cx="60" cy="60" r="54" 
                                    style="stroke-dashoffset: <?= 339 - (($totalMonthlyPoints / ($monthlyGoal * count($users))) * 339) ?>"></circle>
                        </svg>
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
                            <div class="h4 mb-0"><?= round(($totalMonthlyPoints / ($monthlyGoal * count($users))) * 100) ?>%</div>
                            <small class="text-muted">Meta Geral</small>
                        </div>
                    </div>
                    <h6>Meta de Pontos</h6>
                    <p class="text-muted small mb-0"><?= number_format($totalMonthlyPoints) ?> / <?= number_format($monthlyGoal * count($users)) ?></p>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="goal-card text-center">
                    <div class="display-4 text-success mb-2">
                        <i class="fas fa-users"></i>
                    </div>
                    <h6>Usuários Ativos</h6>
                    <div class="h4 text-success"><?= count(array_filter($monthStats, function($s) { return $s['performance']['completed_tasks'] > 0; })) ?></div>
                    <p class="text-muted small mb-0">de <?= count($users) ?> total</p>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="goal-card text-center">
                    <div class="display-4 text-warning mb-2">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <h6>Acima da Meta</h6>
                    <div class="h4 text-warning"><?= count(array_filter($monthStats, function($s) { return $s['goal_progress'] >= 100; })) ?></div>
                    <p class="text-muted small mb-0">usuários</p>
                </div>
            </div>
            
            <div class="col-12 col-md-6 col-lg-3 mb-3">
                <div class="goal-card text-center">
                    <div class="display-4 text-primary mb-2">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h6>Tarefas do Mês</h6>
                    <div class="h4 text-primary"><?= $totalMonthlyTasks ?></div>
                    <p class="text-muted small mb-0">completadas</p>
                </div>
            </div>
        </div>
        
        <!-- Ranking de Performance -->
        <div class="row mb-4">
            <div class="col-12 col-lg-8">
                <div class="goal-card aurora-card">
                    <h5 class="mb-4">🏆 Ranking de Performance Individual</h5>
                    
                    <?php foreach (array_slice($monthStats, 0, 15) as $index => $stat): 
                        $user = $stat['user'];
                        $performance = $stat['performance'];
                        $goalProgress = $stat['goal_progress'];
                        $taskProgress = $stat['task_progress'];
                        
                        // Determinar status
                        $statusClass = 'status-danger';
                        $statusText = 'Crítico';
                        $statusIcon = 'exclamation-triangle';
                        
                        if ($goalProgress >= 100) {
                            $statusClass = 'status-excellent';
                            $statusText = 'Excelente';
                            $statusIcon = 'star';
                        } elseif ($goalProgress >= 75) {
                            $statusClass = 'status-good';
                            $statusText = 'Muito Bom';
                            $statusIcon = 'thumbs-up';
                        } elseif ($goalProgress >= 50) {
                            $statusClass = 'status-warning';
                            $statusText = 'Bom';
                            $statusIcon = 'check';
                        }
                        
                        $initial = strtoupper(substr($user['name'], 0, 1));
                    ?>
                    <div class="user-goal-row">
                        <div class="row align-items-center">
                            <div class="col-auto">
                                <div class="d-flex align-items-center">
                                    <div class="me-3 text-center" style="min-width: 30px;">
                                        <div class="h5 mb-0 <?= $index < 3 ? 'text-warning' : 'text-muted' ?>">
                                            <?php if ($index == 0): ?>
                                                <i class="fas fa-crown"></i>
                                            <?php elseif ($index == 1): ?>
                                                <i class="fas fa-medal"></i>
                                            <?php elseif ($index == 2): ?>
                                                <i class="fas fa-award"></i>
                                            <?php else: ?>
                                                #<?= $index + 1 ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="me-3" style="width: 45px; height: 45px; border-radius: 50%; background: linear-gradient(135deg, var(--secondary-color), var(--success-color)); display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                        <?= $initial ?>
                                    </div>
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($user['name']) ?></h6>
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-<?= $statusIcon ?> <?= $statusClass ?> me-2"></i>
                                            <span class="small <?= $statusClass ?>"><?= $statusText ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col">
                                <div class="row">
                                    <div class="col-12 col-md-6 mb-2 mb-md-0">
                                        <div class="small text-muted mb-1">Meta de Pontos (<?= round($goalProgress) ?>%)</div>
                                        <div class="progress mb-1" style="height: 8px;">
                                            <div class="progress-bar bg-primary" style="width: <?= $goalProgress ?>%"></div>
                                        </div>
                                        <div class="small text-muted">
                                            <?= $performance['total_points'] ?> / <?= $monthlyGoal ?>
                                            <?php if ($stat['points_remaining'] > 0): ?>
                                                <span class="text-warning">(faltam <?= $stat['points_remaining'] ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12 col-md-6">
                                        <div class="small text-muted mb-1">Meta de Tarefas (<?= round($taskProgress) ?>%)</div>
                                        <div class="progress mb-1" style="height: 8px;">
                                            <div class="progress-bar bg-success" style="width: <?= $taskProgress ?>%"></div>
                                        </div>
                                        <div class="small text-muted">
                                            <?= $performance['completed_tasks'] ?> / <?= $taskGoal ?>
                                            <?php if ($stat['tasks_remaining'] > 0): ?>
                                                <span class="text-warning">(faltam <?= $stat['tasks_remaining'] ?>)</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-auto text-end">
                                <div class="h5 mb-0 text-primary"><?= $performance['total_points'] ?></div>
                                <div class="small text-muted"><?= $performance['completed_tasks'] ?> tarefas</div>
                                <div class="small text-muted"><?= $performance['avg_points_per_task'] ?> pts/tarefa</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="col-12 col-lg-4">
                <div class="goal-card aurora-card">
                    <h5 class="mb-4">💰 Tarefas Mais Valiosas</h5>
                    
                    <?php foreach (array_slice($taskAnalysis, 0, 12) as $task): 
                        $valueClass = 'task-value-low';
                        $textClass = 'text-light';
                        
                        if ($task['reward'] >= 200) {
                            $valueClass = 'task-value-high';
                        } elseif ($task['reward'] >= 50) {
                            $valueClass = 'task-value-medium';
                        }
                    ?>
                    <div class="d-flex align-items-center mb-3 p-3 rounded-3 <?= $valueClass ?>">
                        <div class="flex-grow-1">
                            <div class="fw-bold <?= $textClass ?> mb-1" style="font-size: 0.9rem;">
                                <?= htmlspecialchars(substr($task['task'], 0, 40)) ?>
                                <?= strlen($task['task']) > 40 ? '...' : '' ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="h6 mb-0 <?= $textClass ?>">
                                <?= $task['reward'] > 0 ? '+' . $task['reward'] : $task['reward'] ?> pts
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Gráfico de Performance Semanal -->
        <div class="row">
            <div class="col-12">
                <div class="goal-card aurora-card">
                    <h5 class="mb-4">📈 Análise de Performance Mensal</h5>
                    <div style="height: 400px;">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Dados para o gráfico mensal
        const monthlyData = {
            labels: <?= json_encode(array_map(function($s) { return explode(' ', $s['user']['name'])[0]; }, array_slice($monthStats, 0, 10))) ?>,
            datasets: [{
                label: 'Pontos Obtidos',
                data: <?= json_encode(array_map(function($s) { return $s['performance']['total_points']; }, array_slice($monthStats, 0, 10))) ?>,
                backgroundColor: 'rgba(59, 130, 246, 0.8)',
                borderColor: 'rgba(59, 130, 246, 1)',
                borderWidth: 2
            }, {
                label: 'Meta (<?= $monthlyGoal ?> pontos)',
                data: Array(10).fill(<?= $monthlyGoal ?>),
                type: 'line',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                borderColor: 'rgba(239, 68, 68, 0.8)',
                borderWidth: 2,
                borderDash: [5, 5],
                fill: false
            }]
        };
        
        // Configurar gráfico
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: monthlyData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            afterLabel: function(context) {
                                if (context.datasetIndex === 0) {
                                    const progress = Math.round((context.parsed.y / <?= $monthlyGoal ?>) * 100);
                                    return `Meta atingida: ${progress}%`;
                                }
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Pontos'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Usuários'
                        }
                    }
                }
            }
        });
        
        // Animação dos anéis de progresso
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(() => {
                const rings = document.querySelectorAll('.progress-bar-svg');
                rings.forEach(ring => {
                    const offset = ring.style.strokeDashoffset;
                    ring.style.strokeDashoffset = '339';
                    setTimeout(() => {
                        ring.style.strokeDashoffset = offset;
                    }, 100);
                });
            }, 500);
            
            // Inicializar Aurora Background
            const auroraContainer = document.getElementById('aurora-container');
            if (auroraContainer && window.Aurora) {
                new Aurora(auroraContainer, {
                    colorStops: ["#3A29FF", "#FF94B4", "#FF3232"],
                    blend: 0.3,
                    amplitude: 0.8,
                    speed: 0.4
                });
            }
        });
    </script>
    
    <!-- Aurora Effect Script -->
    <script src="aurora.js"></script>
</body>
</html>