<?php
require_once 'backend/AdvboxAPI.php';

$userId = $_GET['id'] ?? null;
if (!$userId) {
    echo '<div class="alert alert-danger">ID do usuário não fornecido</div>';
    exit;
}

$api = new AdvboxAPI();

try {
    $users = $api->getUsers();
    $user = null;
    
    // Encontrar usuário
    foreach ($users as $u) {
        if ($u['id'] == $userId) {
            $user = $u;
            break;
        }
    }
    
    if (!$user) {
        echo '<div class="alert alert-danger">Usuário não encontrado</div>';
        exit;
    }
    
    // Obter performance detalhada
    $performance30 = $api->getUserPerformance($userId, 30);
    $performance7 = $api->getUserPerformance($userId, 7);
    $recentTasks = $api->getUserTasks($userId, date('Y-m-01'), date('Y-m-d'), 20);
    
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Erro ao carregar dados: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

// Função para gerar avatar
function generateAvatar($name) {
    $initials = '';
    $nameParts = explode(' ', $name);
    foreach ($nameParts as $part) {
        if (!empty($part)) {
            $initials .= strtoupper($part[0]);
            if (strlen($initials) >= 2) break;
        }
    }
    return $initials;
}

// Função para determinar cor do avatar baseada no nome
function getAvatarColor($name) {
    $colors = [
        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
        '#06b6d4', '#84cc16', '#f97316', '#ec4899', '#6366f1'
    ];
    $hash = crc32($name);
    return $colors[abs($hash) % count($colors)];
}

$avatarInitials = generateAvatar($user['name']);
$avatarColor = getAvatarColor($user['name']);

// Calcular rank baseado na performance
$allUsers = $api->getTopPerformers(50);
$userRank = 0;
foreach ($allUsers as $index => $performer) {
    if ($performer['user']['id'] == $userId) {
        $userRank = $index + 1;
        break;
    }
}

// Determinar status de performance
$performanceStatus = 'Regular';
$performanceColor = 'warning';
if ($performance30['total_points'] >= 200) {
    $performanceStatus = 'Excelente';
    $performanceColor = 'success';
} elseif ($performance30['total_points'] >= 100) {
    $performanceStatus = 'Muito Bom';
    $performanceColor = 'info';
} elseif ($performance30['total_points'] >= 50) {
    $performanceStatus = 'Bom';
    $performanceColor = 'primary';
}
?>

<div class="container-fluid p-0">
    <!-- Header do Perfil -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex align-items-center p-4" style="background: linear-gradient(135deg, <?= $avatarColor ?>, <?= $avatarColor ?>99); border-radius: 15px;">
                <div class="me-4">
                    <div style="width: 80px; height: 80px; border-radius: 50%; background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center; font-size: 28px; color: white; font-weight: bold; backdrop-filter: blur(10px);">
                        <?= $avatarInitials ?>
                    </div>
                </div>
                <div class="flex-grow-1 text-white">
                    <h3 class="mb-1"><?= htmlspecialchars($user['name']) ?></h3>
                    <p class="mb-2 opacity-75">
                        <i class="fas fa-user me-2"></i>
                        ID: <?= $user['id'] ?>
                        <?php if ($userRank > 0): ?>
                            | Rank: #<?= $userRank ?>
                        <?php endif; ?>
                    </p>
                    <span class="badge bg-light text-dark px-3 py-2">
                        <i class="fas fa-star me-1"></i>
                        <?= $performanceStatus ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Stats Cards -->
    <div class="row mb-4">
        <div class="col-6 col-md-3 mb-2">
            <div class="text-center p-3" style="background: #f8fafc; border-radius: 10px;">
                <div class="h4 text-primary mb-1"><?= $performance30['total_points'] ?></div>
                <small class="text-muted">Pontos (30d)</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="text-center p-3" style="background: #f8fafc; border-radius: 10px;">
                <div class="h4 text-success mb-1"><?= $performance30['completed_tasks'] ?></div>
                <small class="text-muted">Tarefas</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="text-center p-3" style="background: #f8fafc; border-radius: 10px;">
                <div class="h4 text-warning mb-1"><?= $performance30['avg_points_per_task'] ?></div>
                <small class="text-muted">Média</small>
            </div>
        </div>
        <div class="col-6 col-md-3 mb-2">
            <div class="text-center p-3" style="background: #f8fafc; border-radius: 10px;">
                <div class="h4 text-info mb-1"><?= $performance7['total_points'] ?></div>
                <small class="text-muted">Pontos (7d)</small>
            </div>
        </div>
    </div>
    
    <!-- Informações de Contato -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0" style="box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-address-card me-2"></i>
                        Informações de Contato
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">EMAIL</label>
                                <div>
                                    <?php if (!empty($user['email'])): ?>
                                        <i class="fas fa-envelope text-primary me-2"></i>
                                        <a href="mailto:<?= $user['email'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-envelope text-muted me-2"></i>
                                            Não informado
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="text-muted small">TELEFONE</label>
                                <div>
                                    <?php if (!empty($user['cellphone'])): ?>
                                        <i class="fas fa-phone text-success me-2"></i>
                                        <a href="tel:<?= $user['cellphone'] ?>" class="text-decoration-none">
                                            <?= htmlspecialchars($user['cellphone']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">
                                            <i class="fas fa-phone text-muted me-2"></i>
                                            Não informado
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance por Tipo de Tarefa -->
    <?php if (!empty($performance30['tasks_by_type'])): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0" style="box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-pie me-2"></i>
                        Performance por Tipo de Tarefa (30 dias)
                    </h6>
                </div>
                <div class="card-body">
                    <?php 
                    arsort($performance30['tasks_by_type']);
                    $maxCount = max(array_column($performance30['tasks_by_type'], 'count'));
                    foreach (array_slice($performance30['tasks_by_type'], 0, 8, true) as $taskType => $data): 
                        $percentage = $maxCount > 0 ? ($data['count'] / $maxCount) * 100 : 0;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="small"><?= htmlspecialchars($taskType) ?></span>
                            <span class="small text-muted"><?= $data['count'] ?> tarefas | <?= $data['points'] ?> pts</span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar bg-primary" style="width: <?= $percentage ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tarefas Recentes -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0" style="box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">
                        <i class="fas fa-clock me-2"></i>
                        Tarefas Recentes
                    </h6>
                    <span class="badge bg-secondary"><?= isset($recentTasks['totalCount']) ? $recentTasks['totalCount'] : 0 ?> total</span>
                </div>
                <div class="card-body p-0">
                    <?php if (isset($recentTasks['data']) && !empty($recentTasks['data'])): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach (array_slice($recentTasks['data'], 0, 10) as $task): ?>
                                <?php 
                                $userTask = null;
                                foreach ($task['users'] as $taskUser) {
                                    if ($taskUser['user_id'] == $userId) {
                                        $userTask = $taskUser;
                                        break;
                                    }
                                }
                                
                                if ($userTask && !empty($userTask['completed'])):
                                    $completedDate = new DateTime($userTask['completed']);
                                    $isImportant = $userTask['important'] ?? 0;
                                    $isUrgent = $userTask['urgent'] ?? 0;
                                    $points = $task['reward'] ?? 0;
                                    
                                    $pointsClass = 'text-muted';
                                    if ($points > 100) $pointsClass = 'text-success';
                                    elseif ($points > 50) $pointsClass = 'text-primary';
                                    elseif ($points > 0) $pointsClass = 'text-warning';
                                ?>
                                <div class="list-group-item border-0 py-3">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-1">
                                                <h6 class="mb-0 me-2"><?= htmlspecialchars($task['task']) ?></h6>
                                                <?php if ($isImportant): ?>
                                                    <span class="badge bg-warning text-dark me-1">
                                                        <i class="fas fa-star"></i>
                                                    </span>
                                                <?php endif; ?>
                                                <?php if ($isUrgent): ?>
                                                    <span class="badge bg-danger me-1">
                                                        <i class="fas fa-exclamation"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php if (!empty($task['notes'])): ?>
                                                <p class="text-muted small mb-2" style="max-height: 40px; overflow: hidden;">
                                                    <?= htmlspecialchars(substr($task['notes'], 0, 120)) ?>
                                                    <?= strlen($task['notes']) > 120 ? '...' : '' ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex align-items-center text-muted small">
                                                <i class="fas fa-calendar me-1"></i>
                                                <?= $completedDate->format('d/m/Y H:i') ?>
                                                
                                                <?php if (isset($task['lawsuit']['customers'][0]['name'])): ?>
                                                    <span class="ms-3">
                                                        <i class="fas fa-user me-1"></i>
                                                        <?= htmlspecialchars($task['lawsuit']['customers'][0]['name']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="text-end">
                                            <div class="h6 <?= $pointsClass ?> mb-1">
                                                <?= $points > 0 ? '+' . $points : $points ?> pts
                                            </div>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox text-muted" style="font-size: 48px;"></i>
                            <p class="text-muted mt-3">Nenhuma tarefa recente encontrada</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Atividade Diária (últimos 7 dias) -->
    <?php if (!empty($performance7['daily_activity'])): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-0" style="box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <div class="card-header bg-light">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line me-2"></i>
                        Atividade dos Últimos 7 Dias
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <?php 
                        for ($i = 6; $i >= 0; $i--): 
                            $date = date('Y-m-d', strtotime("-{$i} days"));
                            $dayData = $performance7['daily_activity'][$date] ?? ['tasks' => 0, 'points' => 0];
                            $dayName = date('D', strtotime($date));
                            $dayNum = date('d', strtotime($date));
                            
                            $maxDayPoints = max(array_column($performance7['daily_activity'], 'points')) ?: 1;
                            $barHeight = ($dayData['points'] / $maxDayPoints) * 60;
                        ?>
                        <div class="col">
                            <div class="mb-2" style="height: 70px; display: flex; align-items: end; justify-content: center;">
                                <div style="width: 20px; height: <?= $barHeight ?>px; background: linear-gradient(to top, #3b82f6, #60a5fa); border-radius: 10px 10px 0 0; transition: all 0.3s ease;" 
                                     title="<?= $dayData['tasks'] ?> tarefas, <?= $dayData['points'] ?> pontos"></div>
                            </div>
                            <div class="text-center">
                                <div class="small font-weight-bold"><?= $dayNum ?></div>
                                <div class="text-muted" style="font-size: 0.7rem;"><?= $dayName ?></div>
                            </div>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
.progress {
    background-color: #e5e7eb;
}

.list-group-item:hover {
    background-color: #f8fafc;
}

.badge {
    font-size: 0.7rem;
}
</style>