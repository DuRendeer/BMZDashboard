<?php

/**
 * Configurações do Dashboard ADVBOX
 */

class DashboardConfig {
    
    // Configurações da API
    const API_BASE_URL = 'https://app.advbox.com.br/api/v1';
    const API_KEY = '';
    
    // Configurações de Cache
    const CACHE_TTL_DEFAULT = 300; // 5 minutos
    const CACHE_TTL_USERS = 600;   // 10 minutos
    const CACHE_TTL_TASKS = 1800;  // 30 minutos
    
    // Rate Limiting
    const REQUEST_DELAY = 2; // segundos entre requests
    const MAX_RETRIES = 3;
    
    // Configurações de Metas
    const MONTHLY_POINTS_GOAL = 500;  // Meta mensal de pontos por usuário
    const MONTHLY_TASKS_GOAL = 20;    // Meta mensal de tarefas por usuário
    const WEEKLY_POINTS_GOAL = 125;   // Meta semanal de pontos
    const DAILY_POINTS_GOAL = 25;     // Meta diária de pontos
    
    // Configurações do Aurora
    const AURORA_ENABLED = true;
    const AURORA_COLORS = ["#3A29FF", "#FF94B4", "#FF3232"];
    const AURORA_BLEND = 0.4;
    const AURORA_AMPLITUDE = 1.0;
    const AURORA_SPEED = 0.3;
    
    // Configurações de Performance
    const TOP_PERFORMERS_LIMIT = 10;
    const RECENT_TASKS_LIMIT = 50;
    const CHARTS_ANIMATION_DURATION = 1000;
    
    // Configurações de UI
    const ITEMS_PER_PAGE = 20;
    const REFRESH_INTERVAL = 300000; // 5 minutos em millisegundos
    
    // Classificações de Performance
    const PERFORMANCE_LEVELS = [
        'critical' => ['min' => 0, 'max' => 25, 'label' => 'Crítico', 'color' => '#ef4444'],
        'regular' => ['min' => 25, 'max' => 50, 'label' => 'Regular', 'color' => '#6b7280'],
        'good' => ['min' => 50, 'max' => 75, 'label' => 'Bom', 'color' => '#3b82f6'],
        'very_good' => ['min' => 75, 'max' => 100, 'label' => 'Muito Bom', 'color' => '#06b6d4'],
        'excellent' => ['min' => 100, 'max' => 9999, 'label' => 'Excelente', 'color' => '#10b981']
    ];
    
    // Configurações de Notificação
    const ENABLE_NOTIFICATIONS = true;
    const NOTIFICATION_THRESHOLDS = [
        'low_performance' => 25,    // Abaixo de 25% da meta
        'deadline_warning' => 7,    // 7 dias antes do fim do mês
        'goal_achieved' => 100      // 100% da meta atingida
    ];
    
    /**
     * Obter configuração específica
     */
    public static function get($key, $default = null) {
        return defined("self::$key") ? constant("self::$key") : $default;
    }
    
    /**
     * Verificar se uma funcionalidade está habilitada
     */
    public static function isEnabled($feature) {
        $enabledKey = strtoupper($feature) . '_ENABLED';
        return defined("self::$enabledKey") ? constant("self::$enabledKey") : false;
    }
    
    /**
     * Obter configurações do Aurora
     */
    public static function getAuroraConfig() {
        return [
            'enabled' => self::AURORA_ENABLED,
            'colorStops' => self::AURORA_COLORS,
            'blend' => self::AURORA_BLEND,
            'amplitude' => self::AURORA_AMPLITUDE,
            'speed' => self::AURORA_SPEED
        ];
    }
    
    /**
     * Obter configurações de metas
     */
    public static function getGoalsConfig() {
        return [
            'monthly_points' => self::MONTHLY_POINTS_GOAL,
            'monthly_tasks' => self::MONTHLY_TASKS_GOAL,
            'weekly_points' => self::WEEKLY_POINTS_GOAL,
            'daily_points' => self::DAILY_POINTS_GOAL
        ];
    }
    
    /**
     * Classificar performance baseada na porcentagem da meta
     */
    public static function classifyPerformance($percentage) {
        foreach (self::PERFORMANCE_LEVELS as $level => $config) {
            if ($percentage >= $config['min'] && $percentage < $config['max']) {
                return [
                    'level' => $level,
                    'label' => $config['label'],
                    'color' => $config['color'],
                    'percentage' => $percentage
                ];
            }
        }
        
        // Fallback para excelente se acima de todos os níveis
        return [
            'level' => 'excellent',
            'label' => 'Excepcional',
            'color' => '#10b981',
            'percentage' => $percentage
        ];
    }
    
    /**
     * Obter configurações de tema/cores
     */
    public static function getThemeConfig() {
        return [
            'primary' => '#1e3a8a',
            'secondary' => '#3b82f6',
            'success' => '#10b981',
            'warning' => '#f59e0b',
            'danger' => '#ef4444',
            'dark' => '#1f2937'
        ];
    }
}

// Timezone padrão
date_default_timezone_set('America/Sao_Paulo');

// Configurações PHP
ini_set('max_execution_time', 120);
ini_set('memory_limit', '256M');