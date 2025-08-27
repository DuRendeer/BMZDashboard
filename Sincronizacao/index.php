<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);


// Carrega .env
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception('Arquivo .env não encontrado');
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) != 2) continue;
        list($name, $value) = $parts;
        $_ENV[trim($name)] = trim($value);
        $_SERVER[trim($name)] = trim($value);
        putenv(trim($name) . "=" . trim($value));
    }
}

// Configurações padrão se .env não existir
$KOMMO_SUBDOMAIN = 'bmzadvogadosassociados';
$KOMMO_ACCESS_TOKEN = '';
$DB_HOST = '127.0.0.1';
$DB_NAME = 'u406174804_BANCO_BMZ';
$DB_USER = '';
$DB_PASS = '';

// Tentar carregar .env se existir
try {
    loadEnv(__DIR__ . '/.env');
    $KOMMO_SUBDOMAIN = $_ENV['REACT_APP_KOMMO_SUBDOMAIN'] ?? $KOMMO_SUBDOMAIN;
    $KOMMO_ACCESS_TOKEN = $_ENV['REACT_APP_KOMMO_ACCESS_TOKEN'] ?? $KOMMO_ACCESS_TOKEN;
    $DB_HOST = $_ENV['MYSQL_HOST'] ?? $DB_HOST;
    $DB_USER = $_ENV['MYSQL_USER'] ?? $DB_USER;
    $DB_PASS = $_ENV['MYSQL_PASSWORD'] ?? $DB_PASS;
    $DB_NAME = $_ENV['MYSQL_DATABASE'] ?? $DB_NAME;
} catch (Exception $e) {
    // Usar valores padrão
}

// Função para chamadas API
function callAPI($url, $token) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("API Error: HTTP $http_code - $response");
    }
    
    return json_decode($response, true);
}

// Função para chamadas Meta Ads API
function makeAPICall($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception("Meta API Error: HTTP $http_code - $response");
    }
    
    $data = json_decode($response, true);
    if (isset($data['error'])) {
        throw new Exception("Meta API Error: " . $data['error']['message']);
    }
    
    return $data;
}

// Função para extrair campo customizado
function getField($fields, $name) {
    if (!is_array($fields)) return '';
    foreach ($fields as $field) {
        if (($field['field_name'] ?? '') === $name) {
            $value = $field['values'][0]['value'] ?? '';
            if (is_array($value)) {
                return $value['name'] ?? json_encode($value);
            }
            return $value;
        }
    }
    return '';
}

// Funções de conversão de data
function toDate($timestamp) {
    return (is_numeric($timestamp) && $timestamp > 0) ? date('Y-m-d', $timestamp) : null;
}

function toDateTime($timestamp) {
    return (is_numeric($timestamp) && $timestamp > 0) ? date('Y-m-d H:i:s', $timestamp) : null;
}

// Funções para background jobs
function updateStatus($statusFile, $updates) {
    if (file_exists($statusFile)) {
        $status = json_decode(file_get_contents($statusFile), true);
        $status = array_merge($status, $updates);
        $status['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
    }
}

function addLog($statusFile, $message) {
    if (file_exists($statusFile)) {
        $status = json_decode(file_get_contents($statusFile), true);
        $status['logs'][] = [
            'timestamp' => date('Y-m-d H:i:s'),
            'message' => $message
        ];
        file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
    }
}

function processBackgroundJob($jobId, $syncType, $statusFile, $pdo) {
    try {
        updateStatus($statusFile, [
            'status' => 'running',
            'current_task' => 'Conectando ao banco...'
        ]);
        
        if (!$pdo) {
            global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
            $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }
        
        addLog($statusFile, 'Conectado ao banco de dados');
        
        $results = [];
        
        if ($syncType === 'all' || $syncType === 'kommo') {
            updateStatus($statusFile, [
                'current_task' => 'Sincronizando leads do Kommo...',
                'progress' => 10
            ]);
            addLog($statusFile, 'Iniciando sincronização Kommo');
            
            // SINCRONIZAÇÃO REAL DO KOMMO
            global $KOMMO_SUBDOMAIN, $KOMMO_ACCESS_TOKEN;
            
            $kommoResult = ['synced' => 0, 'total_leads' => 0, 'errors' => 0];
            $page = 1;
            $totalSynced = 0;
            
            try {
                while (true) {
                    // Verificar se deve parar
                    $stopFile = __DIR__ . "/stop_{$jobId}.flag";
                    if (file_exists($stopFile)) {
                        addLog($statusFile, "STOP solicitado - interrompendo sincronização Kommo na página $page");
                        updateStatus($statusFile, [
                            'status' => 'stopped',
                            'current_task' => 'Sincronização Kommo interrompida',
                            'stopped_at' => date('Y-m-d H:i:s')
                        ]);
                        unlink($stopFile); // Remove flag
                        return; // Para todo o processamento
                    }
                    
                    updateStatus($statusFile, [
                        'current_task' => "Processando página $page do Kommo (250 leads/página)...",
                        'progress' => min(10 + ($page * 2), 50) // Progresso até 50%
                    ]);
                    
                    addLog($statusFile, "API: Buscando página $page da API do Kommo");
                    
                    // Fazer requisição real para API do Kommo
                    $url = "https://{$KOMMO_SUBDOMAIN}.kommo.com/api/v4/leads?page={$page}&limit=250&with=contacts,custom_fields";
                    $response = callAPI($url, $KOMMO_ACCESS_TOKEN);
                    
                    if (empty($response['_embedded']['leads'])) {
                        addLog($statusFile, "Página $page vazia - fim da sincronização");
                        break;
                    }
                    
                    $leads = $response['_embedded']['leads'];
                    $synced = 0;
                    
                    foreach ($leads as $lead) {
                        try {
                            // Inserir lead real no banco usando a lógica existente
                            $stmt = $pdo->prepare("INSERT INTO leads (kommo_id, nome, price, responsible_user_id, group_id, status_id, pipeline_id, loss_reason_id, created_by, updated_by, created_at, updated_at, closed_at, closest_task_at, is_deleted, equipe, trabalhista_qualidade, primeiro_nome, qualidade_lesao, sexo, validacao_audio_zaias, recebeu_inss, profissao, nome_documento, nacionalidade_documento, estado_civil_documento, profissao_documento, rg_documento, cpf_documento, rua_documento, numero_documento, bairro_documento, cidade_documento, estado_documento, cep_documento, telefone, data_acidente, closer, sdr, prof_epoca, tipo_processo, tipo_acidente, lesao, cep_cidade_cliente, estado, origem, id_ad, data_da_venda, data_nascimento, perito, data_pericia_aux_acid, endereco_pericia_aux, prof_atual, assinatura_procuracao, data_protocolo, cep_comarca, id_anuncio, pericia, pericia_realizada, prop_de_acordo, status_processo, transito_em_julgado, numero_processo, tribunal, data_fim_aux_doenca, data_pgto_bi, r_liquido_bi, arquivado, cpf, tipo_pgto, nota_fiscal, percent_contrato, salarios_minimos, valor_atrasados_total, parte_cliente, recibo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome = VALUES(nome)");
                            
                            // Extrair dados do lead
                            $customFields = $lead['custom_fields_values'] ?? [];
                            
                            $stmt->execute([
                                $lead['id'],
                                $lead['name'] ?? '',
                                $lead['price'] ?? 0,
                                $lead['responsible_user_id'] ?? 0,
                                $lead['group_id'] ?? 0,
                                $lead['status_id'] ?? 0,
                                $lead['pipeline_id'] ?? 0,
                                $lead['loss_reason_id'] ?? null,
                                $lead['created_by'] ?? 0,
                                $lead['updated_by'] ?? 0,
                                toDateTime($lead['created_at']),
                                toDateTime($lead['updated_at']),
                                toDateTime($lead['closed_at']),
                                toDateTime($lead['closest_task_at']),
                                $lead['is_deleted'] ? 1 : 0,
                                getField($customFields, 'Equipe'),
                                getField($customFields, 'Trabalhista Qualidade'),
                                getField($customFields, 'Primeiro Nome'),
                                getField($customFields, 'Qualidade Lesão'),
                                getField($customFields, 'Sexo'),
                                getField($customFields, 'Validação áudio Zaias'),
                                getField($customFields, 'Recebeu INSS'),
                                getField($customFields, 'Profissão'),
                                getField($customFields, 'Nome documento'),
                                getField($customFields, 'Nacionalidade documento'),
                                getField($customFields, 'Estado civil documento'),
                                getField($customFields, 'Profissão documento'),
                                getField($customFields, 'RG documento'),
                                getField($customFields, 'CPF documento'),
                                getField($customFields, 'Rua documento'),
                                getField($customFields, 'Número documento'),
                                getField($customFields, 'Bairro documento'),
                                getField($customFields, 'Cidade documento'),
                                getField($customFields, 'Estado documento'),
                                getField($customFields, 'CEP documento'),
                                getField($customFields, 'Telefone'),
                                getField($customFields, 'Data acidente'),
                                getField($customFields, 'Closer'),
                                getField($customFields, 'SDR'),
                                getField($customFields, 'Prof época'),
                                getField($customFields, 'Tipo processo'),
                                getField($customFields, 'Tipo acidente'),
                                getField($customFields, 'Lesão'),
                                getField($customFields, 'CEP Cidade cliente'),
                                getField($customFields, 'Estado'),
                                getField($customFields, 'Origem'),
                                getField($customFields, 'ID Ad'),
                                getField($customFields, 'Data da venda'),
                                getField($customFields, 'Data nascimento'),
                                getField($customFields, 'Perito'),
                                getField($customFields, 'Data perícia aux acid'),
                                getField($customFields, 'Endereço perícia aux'),
                                getField($customFields, 'Prof atual'),
                                getField($customFields, 'Assinatura procuração'),
                                getField($customFields, 'Data protocolo'),
                                getField($customFields, 'CEP comarca'),
                                getField($customFields, 'ID Anúncio'),
                                getField($customFields, 'Perícia'),
                                getField($customFields, 'Perícia realizada'),
                                getField($customFields, 'Prop de acordo'),
                                getField($customFields, 'Status processo'),
                                getField($customFields, 'Trânsito em julgado'),
                                getField($customFields, 'Número processo'),
                                getField($customFields, 'Tribunal'),
                                getField($customFields, 'Data fim aux doença'),
                                getField($customFields, 'Data pgto BI'),
                                getField($customFields, 'R$ líquido BI'),
                                getField($customFields, 'Arquivado'),
                                getField($customFields, 'CPF'),
                                getField($customFields, 'Tipo pgto'),
                                getField($customFields, 'Nota fiscal'),
                                getField($customFields, 'Percent contrato'),
                                getField($customFields, 'Salários mínimos'),
                                getField($customFields, 'Valor atrasados total'),
                                getField($customFields, 'Parte cliente'),
                                getField($customFields, 'Recibo')
                            ]);
                            
                            $synced++;
                            
                        } catch (Exception $e) {
                            $kommoResult['errors']++;
                            addLog($statusFile, "Erro ao inserir lead {$lead['id']}: " . $e->getMessage());
                        }
                    }
                    
                    $totalSynced += $synced;
                    $kommoResult['synced'] = $totalSynced;
                    // Calcular total estimado baseado nas páginas processadas
                    $kommoResult['total_leads'] = $totalSynced; // Total real processado
                    
                    addLog($statusFile, "Página $page: $synced leads inseridos (Total: $totalSynced)");
                    
                    // Verificar se há próxima página
                    if (!isset($response['_links']['next'])) {
                        addLog($statusFile, "Última página processada - sincronização Kommo concluída");
                        break;
                    }
                    
                    $page++;
                }
                
            } catch (Exception $e) {
                $kommoResult['errors']++;
                addLog($statusFile, "Erro na sincronização Kommo: " . $e->getMessage());
            }
            
            $results['kommo'] = $kommoResult;
            addLog($statusFile, "KOMMO FINALIZADO: {$kommoResult['synced']} leads sincronizados, {$kommoResult['errors']} erros");
        }
        
        if ($syncType === 'all' || $syncType === 'meta') {
            updateStatus($statusFile, [
                'current_task' => 'Sincronizando Meta Ads...',
                'progress' => 60
            ]);
            addLog($statusFile, 'Iniciando sincronização REAL do Meta Ads');
            
            // SINCRONIZAÇÃO REAL DO META ADS
            global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS;
            
            try {
                // Verificar se deve parar
                $stopFile = __DIR__ . "/stop_{$jobId}.flag";
                if (file_exists($stopFile)) {
                    addLog($statusFile, "STOP solicitado - interrompendo Meta Ads");
                    return;
                }
                
                // Recrear tabelas Meta Ads
                updateStatus($statusFile, [
                    'current_task' => 'Criando estrutura Meta Ads...',
                    'progress' => 62
                ]);
                
                $pdo->exec("DROP TABLE IF EXISTS meta_campaigns");
                $pdo->exec("DROP TABLE IF EXISTS meta_adsets"); 
                $pdo->exec("DROP TABLE IF EXISTS meta_ads");
                $pdo->exec("DROP TABLE IF EXISTS meta_creatives");
                
                // Tabela Campanhas
                $pdo->exec("
                CREATE TABLE meta_campaigns (
                    id BIGINT PRIMARY KEY,
                    account_id VARCHAR(50),
                    name TEXT,
                    status VARCHAR(20),
                    objective VARCHAR(50),
                    created_time DATETIME,
                    updated_time DATETIME,
                    start_time DATETIME,
                    stop_time DATETIME,
                    budget_limit DECIMAL(15,2),
                    daily_budget DECIMAL(15,2),
                    lifetime_budget DECIMAL(15,2),
                    bid_strategy VARCHAR(50),
                    buying_type VARCHAR(50),
                    source_campaign_id BIGINT,
                    source_campaign VARCHAR(100),
                    special_ad_categories JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                // Tabela Ad Sets  
                $pdo->exec("
                CREATE TABLE meta_adsets (
                    id BIGINT PRIMARY KEY,
                    account_id VARCHAR(50),
                    campaign_id BIGINT,
                    name TEXT,
                    status VARCHAR(20),
                    created_time DATETIME,
                    updated_time DATETIME,
                    start_time DATETIME,
                    end_time DATETIME,
                    daily_budget DECIMAL(15,2),
                    lifetime_budget DECIMAL(15,2),
                    budget_remaining DECIMAL(15,2),
                    billing_event VARCHAR(50),
                    optimization_goal VARCHAR(50),
                    bid_amount DECIMAL(10,2),
                    targeting JSON,
                    attribution_spec JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                // Tabela Anúncios
                $pdo->exec("
                CREATE TABLE meta_ads (
                    id BIGINT PRIMARY KEY,
                    account_id VARCHAR(50),
                    campaign_id BIGINT,
                    adset_id BIGINT,
                    name TEXT,
                    status VARCHAR(20),
                    created_time DATETIME,
                    updated_time DATETIME,
                    creative JSON,
                    tracking_specs JSON,
                    conversion_specs JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                // Tabela Criativos
                $pdo->exec("
                CREATE TABLE meta_creatives (
                    id BIGINT PRIMARY KEY,
                    name VARCHAR(255),
                    title VARCHAR(255),
                    body TEXT,
                    image_url TEXT,
                    video_url TEXT,
                    thumbnail_url TEXT,
                    call_to_action_type VARCHAR(50),
                    link_url TEXT,
                    object_story_spec JSON,
                    asset_feed_spec JSON,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                )");
                
                addLog($statusFile, 'Estrutura Meta Ads criada com sucesso');
                
                // Configurações Meta Ads
                global $_ENV;
                $accessToken = $_ENV['FACEBOOK_ACCESS_TOKEN'] ?? '';
                $appId = $_ENV['FACEBOOK_APP_ID'] ?? '';
                
                if (empty($accessToken) || empty($appId)) {
                    throw new Exception('Credenciais Meta Ads não configuradas');
                }
                
                // Buscar contas de anúncios
                updateStatus($statusFile, [
                    'current_task' => 'Buscando contas Meta Ads...',
                    'progress' => 65
                ]);
                
                $accounts = [];
                for($i = 1; $i <= 6; $i++) {
                    $key = "BMZ_ACCOUNT_0$i";
                    if(isset($_ENV[$key])) {
                        $accounts[] = $_ENV[$key];
                    }
                }
                
                if (empty($accounts)) {
                    throw new Exception('Nenhuma conta Meta Ads configurada');
                }
                
                $metaResult = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0, 'creatives' => 0, 'errors' => 0];
                
                // Processar cada conta
                foreach ($accounts as $accountIndex => $accountId) {
                    // Verificar se deve parar
                    if (file_exists($stopFile)) {
                        addLog($statusFile, "STOP solicitado - interrompendo Meta Ads na conta $accountId");
                        return;
                    }
                    
                    updateStatus($statusFile, [
                        'current_task' => "Processando conta Meta Ads $accountId (" . ($accountIndex + 1) . "/" . count($accounts) . ")...",
                        'progress' => 65 + ($accountIndex * 5)
                    ]);
                    
                    addLog($statusFile, "Processando conta: act_$accountId");
                    
                    try {
                        // CAMPANHAS
                        $url = "https://graph.facebook.com/v18.0/act_{$accountId}/campaigns?fields=id,name,status,objective,created_time,updated_time,start_time,stop_time,daily_budget,lifetime_budget,bid_strategy,buying_type,source_campaign_id,source_campaign,special_ad_categories&access_token={$accessToken}";
                        $campaignsData = makeAPICall($url);
                        
                        if (isset($campaignsData['data'])) {
                            foreach ($campaignsData['data'] as $campaign) {
                                $stmt = $pdo->prepare("INSERT INTO meta_campaigns (id, account_id, name, status, objective, created_time, updated_time, start_time, stop_time, daily_budget, lifetime_budget, bid_strategy, buying_type, source_campaign_id, source_campaign, special_ad_categories) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status)");
                                
                                $stmt->execute([
                                    $campaign['id'],
                                    $accountId,
                                    $campaign['name'] ?? '',
                                    $campaign['status'] ?? '',
                                    $campaign['objective'] ?? '',
                                    $campaign['created_time'] ?? null,
                                    $campaign['updated_time'] ?? null,
                                    $campaign['start_time'] ?? null,
                                    $campaign['stop_time'] ?? null,
                                    isset($campaign['daily_budget']) ? $campaign['daily_budget'] / 100 : null,
                                    isset($campaign['lifetime_budget']) ? $campaign['lifetime_budget'] / 100 : null,
                                    $campaign['bid_strategy'] ?? null,
                                    $campaign['buying_type'] ?? null,
                                    $campaign['source_campaign_id'] ?? null,
                                    $campaign['source_campaign'] ?? null,
                                    json_encode($campaign['special_ad_categories'] ?? [])
                                ]);
                                
                                $metaResult['campaigns']++;
                            }
                        }
                        
                        // AD SETS
                        $url = "https://graph.facebook.com/v18.0/act_{$accountId}/adsets?fields=id,campaign_id,name,status,created_time,updated_time,start_time,end_time,daily_budget,lifetime_budget,budget_remaining,billing_event,optimization_goal,bid_amount,targeting,attribution_spec&access_token={$accessToken}";
                        $adsetsData = makeAPICall($url);
                        
                        if (isset($adsetsData['data'])) {
                            foreach ($adsetsData['data'] as $adset) {
                                $stmt = $pdo->prepare("INSERT INTO meta_adsets (id, account_id, campaign_id, name, status, created_time, updated_time, start_time, end_time, daily_budget, lifetime_budget, budget_remaining, billing_event, optimization_goal, bid_amount, targeting, attribution_spec) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status)");
                                
                                $stmt->execute([
                                    $adset['id'],
                                    $accountId,
                                    $adset['campaign_id'] ?? null,
                                    $adset['name'] ?? '',
                                    $adset['status'] ?? '',
                                    $adset['created_time'] ?? null,
                                    $adset['updated_time'] ?? null,
                                    $adset['start_time'] ?? null,
                                    $adset['end_time'] ?? null,
                                    isset($adset['daily_budget']) ? $adset['daily_budget'] / 100 : null,
                                    isset($adset['lifetime_budget']) ? $adset['lifetime_budget'] / 100 : null,
                                    isset($adset['budget_remaining']) ? $adset['budget_remaining'] / 100 : null,
                                    $adset['billing_event'] ?? null,
                                    $adset['optimization_goal'] ?? null,
                                    isset($adset['bid_amount']) ? $adset['bid_amount'] / 100 : null,
                                    json_encode($adset['targeting'] ?? []),
                                    json_encode($adset['attribution_spec'] ?? [])
                                ]);
                                
                                $metaResult['adsets']++;
                            }
                        }
                        
                        // ANÚNCIOS
                        $url = "https://graph.facebook.com/v18.0/act_{$accountId}/ads?fields=id,campaign_id,adset_id,name,status,created_time,updated_time,creative,tracking_specs,conversion_specs&access_token={$accessToken}";
                        $adsData = makeAPICall($url);
                        
                        if (isset($adsData['data'])) {
                            foreach ($adsData['data'] as $ad) {
                                $stmt = $pdo->prepare("INSERT INTO meta_ads (id, account_id, campaign_id, adset_id, name, status, created_time, updated_time, creative, tracking_specs, conversion_specs) VALUES (?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), status=VALUES(status)");
                                
                                $stmt->execute([
                                    $ad['id'],
                                    $accountId,
                                    $ad['campaign_id'] ?? null,
                                    $ad['adset_id'] ?? null,
                                    $ad['name'] ?? '',
                                    $ad['status'] ?? '',
                                    $ad['created_time'] ?? null,
                                    $ad['updated_time'] ?? null,
                                    json_encode($ad['creative'] ?? []),
                                    json_encode($ad['tracking_specs'] ?? []),
                                    json_encode($ad['conversion_specs'] ?? [])
                                ]);
                                
                                $metaResult['ads']++;
                            }
                        }
                        
                        addLog($statusFile, "Conta act_$accountId processada com sucesso");
                        
                    } catch (Exception $e) {
                        $metaResult['errors']++;
                        addLog($statusFile, "Erro na conta act_$accountId: " . $e->getMessage());
                    }
                }
                
                addLog($statusFile, "META ADS FINALIZADO: {$metaResult['campaigns']} campanhas, {$metaResult['adsets']} ad sets, {$metaResult['ads']} anúncios sincronizados, {$metaResult['errors']} erros");
                $results['meta'] = $metaResult;
                
            } catch (Exception $e) {
                $metaResult = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0, 'creatives' => 0, 'errors' => 1];
                addLog($statusFile, "Erro geral Meta Ads: " . $e->getMessage());
                $results['meta'] = $metaResult;
            }
        }
        
        if ($syncType === 'all' || $syncType === 'api4com') {
            updateStatus($statusFile, [
                'current_task' => 'Sincronizando Api4com...',
                'progress' => 90
            ]);
            addLog($statusFile, 'Iniciando sincronização REAL da Api4com');
            
            // SINCRONIZAÇÃO REAL DA API4COM
            global $_ENV;
            
            try {
                // Verificar se deve parar
                $stopFile = __DIR__ . "/stop_{$jobId}.flag";
                if (file_exists($stopFile)) {
                    addLog($statusFile, "STOP solicitado - interrompendo Api4com");
                    return;
                }
                
                $apiKey = $_ENV['API4COM_API_KEY'] ?? '';
                
                if (empty($apiKey)) {
                    throw new Exception('API Key da Api4com não configurada');
                }
                
                $api4comResult = ['calls' => 0, 'errors' => 0];
                $page = 1;
                $limit = 250; // Aumentar para 250 registros por página = mais eficiente
                $hasMore = true;
                
                // Definir período - TODOS OS DADOS (remover filtro de data para pegar tudo)
                // Se quiser limitar: $dateFrom = date('Y-m-d', strtotime('-1 year'));
                // Mas para pegar TODOS os 40k+, não usar filtro de data
                
                addLog($statusFile, "Sincronizando TODAS as chamadas disponíveis (40k+ esperados)");
                
                while ($hasMore) {
                    // Verificar se deve parar
                    if (file_exists($stopFile)) {
                        addLog($statusFile, "STOP solicitado - interrompendo Api4com na página $page");
                        return;
                    }
                    
                    updateStatus($statusFile, [
                        'current_task' => "Processando página $page da Api4com ($limit chamadas/página) - Total já processado: {$api4comResult['calls']}...",
                        'progress' => 90 + min(($page - 1) * 0.01, 8) // Progresso mais lento para muitas páginas
                    ]);
                    
                    addLog($statusFile, "API4com: Buscando página $page (total atual: {$api4comResult['calls']})");
                    
                    // Fazer requisição para API4com - SEM FILTRO DE DATA para pegar TODOS os dados
                    $url = "https://api.api4com.com/api/v1/calls?page={$page}&limit={$limit}";
                    
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Authorization: ' . $apiKey,
                        'Content-Type: application/json'
                    ]);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    if ($httpCode !== 200) {
                        throw new Exception("API4com Error: HTTP $httpCode - $response");
                    }
                    
                    $data = json_decode($response, true);
                    
                    if (!isset($data['data']) || empty($data['data'])) {
                        addLog($statusFile, "Página $page vazia - fim da sincronização Api4com");
                        break;
                    }
                    
                    $calls = $data['data'];
                    $synced = 0;
                    
                    foreach ($calls as $call) {
                        try {
                            // Inserir chamada no banco
                            $stmt = $pdo->prepare("INSERT INTO chamadas_api4com (id_chamada, domain, direction, caller, called, started_at, answered_at, ended_at, duration, hangup_cause, hangup_cause_code, record_url, metadata_json, email, first_name, last_name, bina) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE domain=VALUES(domain), direction=VALUES(direction), duration=VALUES(duration), hangup_cause=VALUES(hangup_cause), record_url=VALUES(record_url)");
                            
                            $stmt->execute([
                                $call['id'] ?? '',
                                $call['domain'] ?? '',
                                $call['direction'] ?? 'outbound',
                                $call['caller'] ?? '',
                                $call['called'] ?? '',
                                isset($call['started_at']) ? date('Y-m-d H:i:s', strtotime($call['started_at'])) : null,
                                isset($call['answered_at']) ? date('Y-m-d H:i:s', strtotime($call['answered_at'])) : null,
                                isset($call['ended_at']) ? date('Y-m-d H:i:s', strtotime($call['ended_at'])) : null,
                                $call['duration'] ?? 0,
                                $call['hangup_cause'] ?? '',
                                $call['hangup_cause_code'] ?? null,
                                $call['record_url'] ?? '',
                                json_encode($call['metadata'] ?? []),
                                $call['email'] ?? '',
                                $call['first_name'] ?? '',
                                $call['last_name'] ?? '',
                                $call['bina'] ?? ''
                            ]);
                            
                            $synced++;
                            
                        } catch (Exception $e) {
                            $api4comResult['errors']++;
                            addLog($statusFile, "Erro ao inserir chamada {$call['id']}: " . $e->getMessage());
                        }
                    }
                    
                    $api4comResult['calls'] += $synced;
                    addLog($statusFile, "Página $page: $synced chamadas inseridas (Total: {$api4comResult['calls']})");
                    
                    // Verificar se há mais páginas - múltiplas maneiras de detecção
                    $hasMore = false;
                    
                    // Método 1: Verificar campo pagination.has_more
                    if (isset($data['pagination']['has_more'])) {
                        $hasMore = $data['pagination']['has_more'];
                        addLog($statusFile, "Pagination has_more: " . ($hasMore ? 'true' : 'false'));
                    }
                    // Método 2: Verificar se retornou menos dados que o limite
                    else if (count($calls) >= $limit) {
                        $hasMore = true;
                        addLog($statusFile, "Retornou " . count($calls) . " itens (limite: $limit), assumindo mais páginas");
                    }
                    // Método 3: Verificar campo total_pages se existir
                    else if (isset($data['pagination']['total_pages']) && $page < $data['pagination']['total_pages']) {
                        $hasMore = true;
                        addLog($statusFile, "Página $page de {$data['pagination']['total_pages']}, continuando");
                    }
                    // Método 4: Verificar se existe next_page_url
                    else if (isset($data['pagination']['next_page_url']) && !empty($data['pagination']['next_page_url'])) {
                        $hasMore = true;
                        addLog($statusFile, "Existe next_page_url, continuando");
                    }
                    
                    addLog($statusFile, "DEBUG - Página $page: " . count($calls) . " chamadas retornadas, hasMore: " . ($hasMore ? 'SIM' : 'NÃO'));
                    
                    if (!$hasMore) {
                        addLog($statusFile, "Não há mais páginas - sincronização Api4com concluída");
                        break;
                    }
                    
                    $page++;
                    
                    // Limite de segurança para não ficar em loop infinito - ajustado para 40k+ registros
                    if ($page > 200) { // 200 páginas x 250 = 50k registros máximo
                        addLog($statusFile, "Limite de páginas atingido (200) - interrompendo sincronização Api4com com {$api4comResult['calls']} chamadas");
                        break;
                    }
                    
                    // A cada 10 páginas, mostrar progresso
                    if ($page % 10 === 0) {
                        addLog($statusFile, "PROGRESSO: Página $page processada - Total: {$api4comResult['calls']} chamadas sincronizadas");
                    }
                }
                
                addLog($statusFile, "API4COM FINALIZADO: {$api4comResult['calls']} chamadas sincronizadas, {$api4comResult['errors']} erros");
                $results['api4com'] = $api4comResult;
                
            } catch (Exception $e) {
                $api4comResult = ['calls' => 0, 'errors' => 1];
                addLog($statusFile, "Erro geral Api4com: " . $e->getMessage());
                $results['api4com'] = $api4comResult;
            }
        }
        
        updateStatus($statusFile, [
            'status' => 'completed',
            'current_task' => 'Sincronização concluída',
            'progress' => 100,
            'completed_at' => date('Y-m-d H:i:s'),
            'results' => $results
        ]);
        
        addLog($statusFile, 'Sincronização concluída com sucesso');
        
    } catch (Exception $e) {
        updateStatus($statusFile, [
            'status' => 'error',
            'current_task' => 'Erro na sincronização',
            'error' => $e->getMessage(),
            'failed_at' => date('Y-m-d H:i:s')
        ]);
        
        addLog($statusFile, 'ERRO: ' . $e->getMessage());
    }
}

// Processamento AJAX - Suportar GET e POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']))) {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        if ($action === 'check_table') {
            header('Content-Type: application/json; charset=utf-8');
            
            $tables = $pdo->query("SHOW TABLES LIKE 'leads'")->fetchAll();
            if (empty($tables)) {
                echo json_encode(['exists' => false, 'message' => 'Tabela leads não existe']);
                exit;
            }
            
            $columns = $pdo->query("DESCRIBE leads")->fetchAll();
            $count = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
            
            echo json_encode([
                'exists' => true,
                'columns' => count($columns),
                'records' => $count,
                'structure' => $columns
            ]);
            exit;
        }
        
        elseif ($action === 'fix_table') {
            header('Content-Type: application/json; charset=utf-8');
            
            // Manter apenas tabela leads e criar estrutura completa Meta Ads
            $sql = "
            -- Limpar e recriar estrutura completa
            DROP TABLE IF EXISTS meta_campaigns;
            DROP TABLE IF EXISTS meta_adsets; 
            DROP TABLE IF EXISTS meta_ads;
            DROP TABLE IF EXISTS meta_creatives;
            
            -- Manter leads como está
            CREATE TABLE IF NOT EXISTS leads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                kommo_id BIGINT UNIQUE NOT NULL,
                nome VARCHAR(255),
                telefone VARCHAR(255),
                id_ad VARCHAR(255),
                created_at DATETIME,
                INDEX idx_id_ad (id_ad)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- 1. CAMPANHAS
            CREATE TABLE meta_campaigns (
                id INT AUTO_INCREMENT PRIMARY KEY,
                campaign_id VARCHAR(50) UNIQUE NOT NULL,
                account_id VARCHAR(50) NOT NULL,
                name VARCHAR(500),
                status VARCHAR(20),
                objective VARCHAR(100),
                daily_budget DECIMAL(15,2),
                lifetime_budget DECIMAL(15,2),
                bid_strategy VARCHAR(50),
                buying_type VARCHAR(50),
                start_time DATETIME,
                stop_time DATETIME,
                effective_status VARCHAR(20),
                
                -- Métricas agregadas
                total_spend DECIMAL(15,2) DEFAULT 0,
                total_impressions BIGINT DEFAULT 0,
                total_clicks BIGINT DEFAULT 0,
                total_reach BIGINT DEFAULT 0,
                cpc DECIMAL(10,4) DEFAULT 0,
                cpm DECIMAL(10,4) DEFAULT 0,
                ctr DECIMAL(10,4) DEFAULT 0,
                
                created_time DATETIME,
                updated_time DATETIME,
                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_campaign_id (campaign_id),
                INDEX idx_account_id (account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- 2. AD SETS  
            CREATE TABLE meta_adsets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                adset_id VARCHAR(50) UNIQUE NOT NULL,
                campaign_id VARCHAR(50) NOT NULL,
                name VARCHAR(500),
                status VARCHAR(20),
                daily_budget DECIMAL(15,2),
                lifetime_budget DECIMAL(15,2),
                optimization_goal VARCHAR(50),
                billing_event VARCHAR(50),
                start_time DATETIME,
                end_time DATETIME,
                
                -- Segmentação
                targeting_json TEXT,
                age_min INT,
                age_max INT,
                
                -- Métricas
                spend DECIMAL(15,2) DEFAULT 0,
                impressions BIGINT DEFAULT 0,
                clicks BIGINT DEFAULT 0,
                reach BIGINT DEFAULT 0,
                
                created_time DATETIME,
                updated_time DATETIME,
                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_adset_id (adset_id),
                INDEX idx_campaign_id (campaign_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- 3. ANÚNCIOS (Principal - onde ficam os dados detalhados)
            CREATE TABLE meta_ads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ad_id VARCHAR(50) UNIQUE NOT NULL,
                campaign_id VARCHAR(50) NOT NULL,
                adset_id VARCHAR(50) NOT NULL,
                
                -- Informações básicas
                name VARCHAR(500),
                status VARCHAR(20),
                
                -- Links e previews
                preview_url TEXT,
                permalink_url TEXT,
                
                -- Métricas detalhadas
                spend DECIMAL(15,2) DEFAULT 0,
                impressions BIGINT DEFAULT 0,
                clicks BIGINT DEFAULT 0,
                reach BIGINT DEFAULT 0,
                frequency DECIMAL(10,4) DEFAULT 0,
                cpc DECIMAL(10,4) DEFAULT 0,
                cpm DECIMAL(10,4) DEFAULT 0,
                ctr DECIMAL(10,4) DEFAULT 0,
                conversions INT DEFAULT 0,
                cost_per_result DECIMAL(10,4) DEFAULT 0,
                video_views INT DEFAULT 0,
                unique_clicks INT DEFAULT 0,
                inline_link_clicks INT DEFAULT 0,
                outbound_clicks INT DEFAULT 0,
                effective_status VARCHAR(20),
                configured_status VARCHAR(20),
                tracking_specs TEXT,
                
                -- Conversões
                conversion_rate DECIMAL(10,4) DEFAULT 0,
                cost_per_conversion DECIMAL(10,4) DEFAULT 0,
                
                -- Datas
                created_time DATETIME,
                updated_time DATETIME,
                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_ad_id (ad_id),
                INDEX idx_campaign_id (campaign_id),
                INDEX idx_adset_id (adset_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            
            -- 4. CRIATIVOS (Imagens/Vídeos)
            CREATE TABLE meta_creatives (
                id INT AUTO_INCREMENT PRIMARY KEY,
                creative_id VARCHAR(50) UNIQUE NOT NULL,
                ad_id VARCHAR(50) NOT NULL,
                
                -- Dados do criativo
                title VARCHAR(500),
                body TEXT,
                call_to_action VARCHAR(100),
                
                -- Mídia
                image_url TEXT,
                video_url TEXT,
                thumbnail_url TEXT,
                
                -- Tipo
                creative_type VARCHAR(50), -- image, video, carousel, etc
                
                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                
                INDEX idx_creative_id (creative_id),
                INDEX idx_ad_id (ad_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            $pdo->exec($sql);
            echo json_encode(['success' => true, 'message' => 'Estrutura Meta Ads completa criada: meta_campaigns, meta_adsets, meta_ads, meta_creatives']);
            exit;
        }
        
        elseif ($action === 'test_lead') {
            header('Content-Type: application/json; charset=utf-8');
            
            $leadId = $_POST['lead_id'] ?? 13846830;
            $url = "https://{$KOMMO_SUBDOMAIN}.kommo.com/api/v4/leads/{$leadId}?with=contacts";
            
            $response = callAPI($url, $KOMMO_ACCESS_TOKEN);
            
            // Mapear campos
            $campos = [];
            if (isset($response['custom_fields_values'])) {
                foreach ($response['custom_fields_values'] as $field) {
                    $name = $field['field_name'] ?? '';
                    $value = $field['values'][0]['value'] ?? '';
                    
                    if ($name === 'CPF' && is_array($value)) {
                        $campos[$name] = $value['name'] ?? '';
                    } elseif ($name === 'CEP/Cidade Cliente' && is_array($value)) {
                        $campos[$name] = json_encode($value);
                    } else {
                        $campos[$name] = $value;
                    }
                }
            }
            
            // Deletar se existe
            $pdo->exec("DELETE FROM leads WHERE kommo_id = " . $response['id']);
            
            // Inserir
            $stmt = $pdo->prepare("INSERT INTO leads (kommo_id, nome, price, responsible_user_id, group_id, status_id, pipeline_id, loss_reason_id, created_by, updated_by, created_at, updated_at, closed_at, closest_task_at, is_deleted, equipe, trabalhista_qualidade, primeiro_nome, qualidade_lesao, sexo, validacao_audio_zaias, recebeu_inss, profissao, nome_documento, nacionalidade_documento, estado_civil_documento, profissao_documento, rg_documento, cpf_documento, rua_documento, numero_documento, bairro_documento, cidade_documento, estado_documento, cep_documento, telefone, data_acidente, closer, sdr, prof_epoca, tipo_processo, tipo_acidente, lesao, cep_cidade_cliente, estado, origem, id_ad, data_da_venda, data_nascimento, perito, data_pericia_aux_acid, endereco_pericia_aux, prof_atual, assinatura_procuracao, data_protocolo, cep_comarca, id_anuncio, pericia, pericia_realizada, prop_de_acordo, status_processo, transito_em_julgado, numero_processo, tribunal, data_fim_aux_doenca, data_pgto_bi, r_liquido_bi, arquivado, cpf, tipo_pgto, nota_fiscal, percent_contrato, salarios_minimos, valor_atrasados_total, parte_cliente, recibo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome = VALUES(nome)");
            
            $values = [
                $response['id'], $response['name'] ?? '', $response['price'] ?? null, $response['responsible_user_id'] ?? null, $response['group_id'] ?? null, $response['status_id'] ?? null, $response['pipeline_id'] ?? null, $response['loss_reason_id'] ?? null, $response['created_by'] ?? null, $response['updated_by'] ?? null, toDateTime($response['created_at'] ?? null), toDateTime($response['updated_at'] ?? null), toDateTime($response['closed_at'] ?? null), toDateTime($response['closest_task_at'] ?? null), $response['is_deleted'] ?? false,
                $campos['Equipe'] ?? '', $campos['Trabalhista Qualidade'] ?? '', $campos['1º Nome'] ?? '', $campos['Qualidade Lesão'] ?? '', $campos['Sexo'] ?? '', $campos['Validação Áudio Zaias'] ?? '', $campos['Recebeu INSS'] ?? '', $campos['Profissão'] ?? '', $campos['Nome Documento'] ?? '', $campos['Nacionalidade Documento'] ?? '', $campos['Estado Civil Documento'] ?? '', $campos['Profissão Documento'] ?? '', $campos['Rg Documento'] ?? '', $campos['CPF Documento'] ?? '', $campos['Rua Documento'] ?? '', $campos['Número Documento'] ?? '', $campos['Bairro Documento'] ?? '', $campos['Cidade Documento'] ?? '', $campos['Estado Documento'] ?? '', $campos['Cep Documento'] ?? '', $campos['Telefone'] ?? '', toDate($campos['Data Acidente'] ?? null), $campos['Closer'] ?? '', $campos['SDR'] ?? '', $campos['Prof. Época'] ?? '', $campos['Tipo Processo'] ?? '', $campos['Tipo Acidente'] ?? '', $campos['Lesão'] ?? '', $campos['CEP/Cidade Cliente'] ?? '', $campos['Estado'] ?? '', $campos['Origem'] ?? '', $campos['ID Ad'] ?? '', toDate($campos['Data da Venda'] ?? null), toDate($campos['Data de nascimento'] ?? null), $campos['Perito'] ?? '', toDateTime($campos['Data Perícia Aux Acid'] ?? null), $campos['Endereço Perícia Aux'] ?? '', $campos['Prof. Atual'] ?? '', toDate($campos['Assinatura Procuração'] ?? null), toDate($campos['Data Protocolo'] ?? null), $campos['CEP Comarca'] ?? '', $campos['ID Anuncio'] ?? '', $campos['Perícia'] ?? '', $campos['Perícia Realizada?'] ?? '', $campos['Prop. de Acordo'] ?? '', $campos['Status Processo'] ?? '', toDate($campos['Trânsito em Julgado'] ?? null), $campos['Nº Processo'] ?? '', $campos['Tribunal'] ?? '', toDate($campos['Data Fim Aux. Doença'] ?? null), toDate($campos['Data Pgto BI'] ?? null), $campos['R$ Líquido BI'] ?? null, $campos['Arquivado'] ?? '', $campos['CPF'] ?? '', $campos['Tipo Pgto'] ?? '', $campos['Nota Fiscal'] ?? '', $campos['% do Contrato'] ?? '', $campos['Salários Mínimos'] ?? null, $campos['Valor Atrasados Total R$'] ?? '', $campos['Parte Cliente'] ?? '', ($campos['Recibo'] ?? false) ? 1 : 0
            ];
            
            $result = $stmt->execute($values);
            
            echo json_encode([
                'success' => $result,
                'lead_data' => $response,
                'mapped_fields' => $campos,
                'rows_affected' => $stmt->rowCount()
            ]);
            exit;
        }
        
        elseif ($action === 'daemon_control') {
            header('Content-Type: application/json; charset=utf-8');
            
            $cmd = $_POST['cmd'] ?? '';
            $output = '';
            $statusFile = __DIR__ . '/daemon_status.json';
            $pidFile = __DIR__ . '/daemon.pid';
            
            switch ($cmd) {
                case 'start':
                    // Verificar se já está rodando
                    if (file_exists($statusFile)) {
                        $status = json_decode(file_get_contents($statusFile), true);
                        if ($status && $status['active']) {
                            $output = 'Daemon já está rodando';
                            break;
                        }
                    }
                    
                    // Iniciar daemon direto no PHP (sem exec)
                    $nextSync = time() + 300; // 5 minutos para teste (depois mude para 5400 = 90 min)
                    $statusData = [
                        'active' => true,
                        'status' => 'running',
                        'last_update' => time(),
                        'next_sync' => $nextSync,
                        'started_at' => time()
                    ];
                    file_put_contents($statusFile, json_encode($statusData, JSON_PRETTY_PRINT));
                    
                    // Agendar próxima sincronização usando PHP session
                    if (!isset($_SESSION)) session_start();
                    $_SESSION['daemon_next_sync'] = $nextSync;
                    $_SESSION['daemon_active'] = true;
                    
                    $output = 'Daemon iniciado com sucesso';
                    break;
                    
                case 'stop':
                    if (file_exists($statusFile)) {
                        unlink($statusFile);
                    }
                    if (file_exists($pidFile)) {
                        unlink($pidFile);
                    }
                    if (!isset($_SESSION)) session_start();
                    $_SESSION['daemon_active'] = false;
                    unset($_SESSION['daemon_next_sync']);
                    
                    $output = 'Daemon parado';
                    break;
                    
                case 'status':
                    if (file_exists($statusFile)) {
                        $status = json_decode(file_get_contents($statusFile), true);
                        if ($status && $status['active']) {
                            $nextSyncFormatted = date('Y-m-d H:i:s', $status['next_sync']);
                            $output = "🤖 Status: ATIVO\n⏰ Próxima sincronização: $nextSyncFormatted";
                        } else {
                            $output = "⏸️ Status: INATIVO";
                        }
                    } else {
                        $output = "⏸️ Status: INATIVO";
                    }
                    break;
            }
            
            echo json_encode(['success' => true, 'output' => $output]);
            exit;
        }
        
        elseif ($action === 'meta_test') {
            header('Content-Type: application/json; charset=utf-8');
            
            $testType = $_POST['test_type'] ?? 'connection';
            $accessToken = $_ENV['FACEBOOK_ACCESS_TOKEN'] ?? '';
            $appId = $_ENV['FACEBOOK_APP_ID'] ?? '';
            
            try {
                switch ($testType) {
                    case 'connection':
                        // Testar conexão básica e listar contas de anúncios disponíveis
                        $url = "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            throw new Exception("HTTP Error $httpCode: $response");
                        }
                        
                        $data = json_decode($response, true);
                        
                        if (isset($data['error'])) {
                            throw new Exception("Erro da API: " . $data['error']['message']);
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Conexão estabelecida com sucesso!',
                            'data' => $data,
                            'available_accounts' => $data['data'] ?? []
                        ]);
                        break;
                        
                    case 'campaigns':
                        // Primeiro, listar contas disponíveis para usar a primeira válida
                        $accountsUrl = "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $accountsUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $accountsResponse = curl_exec($ch);
                        curl_close($ch);
                        
                        $accountsData = json_decode($accountsResponse, true);
                        
                        if (!isset($accountsData['data']) || empty($accountsData['data'])) {
                            throw new Exception("Nenhuma conta de anúncios encontrada ou acessível");
                        }
                        
                        // Usar a primeira conta disponível
                        $firstAccount = $accountsData['data'][0];
                        $accountId = $firstAccount['id'];
                        
                        // Buscar campanhas da primeira conta válida
                        $url = "https://graph.facebook.com/v21.0/{$accountId}/campaigns?fields=id,name,status,objective,daily_budget,lifetime_budget,bid_strategy,buying_type,start_time,stop_time,effective_status,created_time,updated_time&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            throw new Exception("HTTP Error $httpCode ao buscar campanhas: $response");
                        }
                        
                        $data = json_decode($response, true);
                        
                        if (isset($data['error'])) {
                            throw new Exception("Erro da API: " . $data['error']['message'] . " (Code: " . ($data['error']['code'] ?? 'N/A') . ")");
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Campanhas obtidas com sucesso!',
                            'data' => $data,
                            'account' => $accountId,
                            'account_name' => $firstAccount['name'] ?? 'N/A',
                            'available_accounts' => $accountsData['data']
                        ]);
                        break;
                        
                    case 'adsets':
                        // Primeiro, listar contas disponíveis para usar a primeira válida
                        $accountsUrl = "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $accountsUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $accountsResponse = curl_exec($ch);
                        curl_close($ch);
                        
                        $accountsData = json_decode($accountsResponse, true);
                        
                        if (!isset($accountsData['data']) || empty($accountsData['data'])) {
                            throw new Exception("Nenhuma conta de anúncios encontrada ou acessível");
                        }
                        
                        // Usar a primeira conta disponível
                        $firstAccount = $accountsData['data'][0];
                        $accountId = $firstAccount['id'];
                        
                        // Buscar ad sets da primeira conta válida
                        $url = "https://graph.facebook.com/v21.0/{$accountId}/adsets?fields=id,name,status,campaign_id,daily_budget,lifetime_budget,optimization_goal,billing_event,targeting,start_time,end_time,created_time,updated_time&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            throw new Exception("HTTP Error $httpCode ao buscar ad sets: $response");
                        }
                        
                        $data = json_decode($response, true);
                        
                        if (isset($data['error'])) {
                            throw new Exception("Erro da API: " . $data['error']['message'] . " (Code: " . ($data['error']['code'] ?? 'N/A') . ")");
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Ad Sets obtidos com sucesso!',
                            'data' => $data,
                            'account' => $accountId,
                            'account_name' => $firstAccount['name'] ?? 'N/A',
                            'available_accounts' => $accountsData['data']
                        ]);
                        break;
                        
                    case 'ads':
                        // Primeiro, listar contas disponíveis para usar a primeira válida
                        $accountsUrl = "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $accountsUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $accountsResponse = curl_exec($ch);
                        curl_close($ch);
                        
                        $accountsData = json_decode($accountsResponse, true);
                        
                        if (!isset($accountsData['data']) || empty($accountsData['data'])) {
                            throw new Exception("Nenhuma conta de anúncios encontrada ou acessível");
                        }
                        
                        // Usar a primeira conta disponível
                        $firstAccount = $accountsData['data'][0];
                        $accountId = $firstAccount['id'];
                        
                        // Buscar anúncios da primeira conta válida
                        $url = "https://graph.facebook.com/v21.0/{$accountId}/ads?fields=id,name,status,adset_id,campaign_id,effective_status,configured_status,tracking_specs,created_time,updated_time,creative&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            throw new Exception("HTTP Error $httpCode ao buscar anúncios: $response");
                        }
                        
                        $data = json_decode($response, true);
                        
                        if (isset($data['error'])) {
                            throw new Exception("Erro da API: " . $data['error']['message'] . " (Code: " . ($data['error']['code'] ?? 'N/A') . ")");
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Anúncios obtidos com sucesso!',
                            'data' => $data,
                            'account' => $accountId,
                            'account_name' => $firstAccount['name'] ?? 'N/A',
                            'available_accounts' => $accountsData['data']
                        ]);
                        break;
                        
                    case 'insights':
                        // Primeiro, listar contas disponíveis para usar a primeira válida
                        $accountsUrl = "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $accountsUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $accountsResponse = curl_exec($ch);
                        curl_close($ch);
                        
                        $accountsData = json_decode($accountsResponse, true);
                        
                        if (!isset($accountsData['data']) || empty($accountsData['data'])) {
                            throw new Exception("Nenhuma conta de anúncios encontrada ou acessível");
                        }
                        
                        // Usar a primeira conta disponível
                        $firstAccount = $accountsData['data'][0];
                        $accountId = $firstAccount['id'];
                        
                        // Buscar métricas dos últimos 30 dias
                        $dateFrom = date('Y-m-d', strtotime('-30 days'));
                        $dateTo = date('Y-m-d');
                        
                        $url = "https://graph.facebook.com/v21.0/{$accountId}/insights?fields=campaign_id,campaign_name,adset_id,adset_name,ad_id,ad_name,impressions,clicks,spend,cpc,cpm,ctr,reach,frequency,conversions,cost_per_result,unique_clicks,inline_link_clicks,outbound_clicks,actions,action_values&time_range={'since':'$dateFrom','until':'$dateTo'}&level=ad&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            throw new Exception("HTTP Error $httpCode ao buscar métricas: $response");
                        }
                        
                        $data = json_decode($response, true);
                        
                        if (isset($data['error'])) {
                            throw new Exception("Erro da API: " . $data['error']['message'] . " (Code: " . ($data['error']['code'] ?? 'N/A') . ")");
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Métricas obtidas com sucesso!',
                            'data' => $data,
                            'account' => $accountId,
                            'account_name' => $firstAccount['name'] ?? 'N/A',
                            'date_range' => "$dateFrom até $dateTo",
                            'available_accounts' => $accountsData['data']
                        ]);
                        break;
                        
                    case 'sync_meta':
                        // Sincronização COMPLETA do Meta Ads
                        $accountsUrl = "https://graph.facebook.com/v21.0/me/adaccounts?fields=id,name,account_status&access_token=" . $accessToken;
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $accountsUrl);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        $accountsResponse = curl_exec($ch);
                        curl_close($ch);
                        
                        $accountsData = json_decode($accountsResponse, true);
                        
                        if (!isset($accountsData['data']) || empty($accountsData['data'])) {
                            throw new Exception("Nenhuma conta de anúncios encontrada ou acessível");
                        }
                        
                        // RECRIAR TABELAS COM ESTRUTURA CORRETA
                        try {
                            // Dropar tabelas existentes para recriar com estrutura correta
                            $pdo->exec("DROP TABLE IF EXISTS meta_campaigns");
                            $pdo->exec("DROP TABLE IF EXISTS meta_adsets");
                            $pdo->exec("DROP TABLE IF EXISTS meta_ads");
                            $pdo->exec("DROP TABLE IF EXISTS meta_creatives");
                            // Criar estrutura completa
                            $createTablesSQL = "
                            CREATE TABLE meta_campaigns (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                campaign_id VARCHAR(50) UNIQUE NOT NULL,
                                account_id VARCHAR(50) NOT NULL,
                                name VARCHAR(500),
                                status VARCHAR(20),
                                objective VARCHAR(100),
                                daily_budget DECIMAL(15,2),
                                lifetime_budget DECIMAL(15,2),
                                bid_strategy VARCHAR(50),
                                buying_type VARCHAR(50),
                                start_time DATETIME,
                                stop_time DATETIME,
                                effective_status VARCHAR(20),
                                total_spend DECIMAL(15,2) DEFAULT 0,
                                total_impressions BIGINT DEFAULT 0,
                                total_clicks BIGINT DEFAULT 0,
                                total_reach BIGINT DEFAULT 0,
                                cpc DECIMAL(10,4) DEFAULT 0,
                                cpm DECIMAL(10,4) DEFAULT 0,
                                ctr DECIMAL(10,4) DEFAULT 0,
                                created_time DATETIME,
                                updated_time DATETIME,
                                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_campaign_id (campaign_id),
                                INDEX idx_account_id (account_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                            CREATE TABLE meta_adsets (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                adset_id VARCHAR(50) UNIQUE NOT NULL,
                                campaign_id VARCHAR(50) NOT NULL,
                                account_id VARCHAR(50) NOT NULL,
                                name VARCHAR(500),
                                status VARCHAR(20),
                                targeting_json TEXT,
                                age_min INT,
                                age_max INT,
                                daily_budget DECIMAL(15,2),
                                lifetime_budget DECIMAL(15,2),
                                optimization_goal VARCHAR(100),
                                billing_event VARCHAR(50),
                                start_time DATETIME,
                                end_time DATETIME,
                                created_time DATETIME,
                                updated_time DATETIME,
                                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_adset_id (adset_id),
                                INDEX idx_campaign_id (campaign_id),
                                INDEX idx_account_id (account_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                            CREATE TABLE meta_ads (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                ad_id VARCHAR(50) UNIQUE NOT NULL,
                                campaign_id VARCHAR(50) NOT NULL,
                                adset_id VARCHAR(50) NOT NULL,
                                account_id VARCHAR(50) NOT NULL,
                                name VARCHAR(500),
                                status VARCHAR(20),
                                preview_url TEXT,
                                permalink_url TEXT,
                                spend DECIMAL(15,2) DEFAULT 0,
                                impressions BIGINT DEFAULT 0,
                                clicks BIGINT DEFAULT 0,
                                reach BIGINT DEFAULT 0,
                                frequency DECIMAL(10,4) DEFAULT 0,
                                cpc DECIMAL(10,4) DEFAULT 0,
                                cpm DECIMAL(10,4) DEFAULT 0,
                                ctr DECIMAL(10,4) DEFAULT 0,
                                conversions INT DEFAULT 0,
                                cost_per_result DECIMAL(10,4) DEFAULT 0,
                                unique_clicks INT DEFAULT 0,
                                inline_link_clicks INT DEFAULT 0,
                                outbound_clicks INT DEFAULT 0,
                                effective_status VARCHAR(20),
                                configured_status VARCHAR(20),
                                tracking_specs TEXT,
                                conversion_rate DECIMAL(10,4) DEFAULT 0,
                                cost_per_conversion DECIMAL(10,4) DEFAULT 0,
                                created_time DATETIME,
                                updated_time DATETIME,
                                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_ad_id (ad_id),
                                INDEX idx_campaign_id (campaign_id),
                                INDEX idx_adset_id (adset_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

                            CREATE TABLE meta_creatives (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                creative_id VARCHAR(50) UNIQUE NOT NULL,
                                ad_id VARCHAR(50) NOT NULL,
                                title VARCHAR(500),
                                body TEXT,
                                call_to_action VARCHAR(100),
                                image_url TEXT,
                                video_url TEXT,
                                thumbnail_url TEXT,
                                creative_type VARCHAR(50),
                                last_sync DATETIME DEFAULT CURRENT_TIMESTAMP,
                                INDEX idx_creative_id (creative_id),
                                INDEX idx_ad_id (ad_id)
                            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                            ";
                            
                            $pdo->exec($createTablesSQL);
                        } catch (Exception $e) {
                            // Se der erro, continuar mesmo assim
                        }

                        $totalSynced = ['campaigns' => 0, 'adsets' => 0, 'ads' => 0, 'creatives' => 0];
                        $errors = [];
                        
                        foreach ($accountsData['data'] as $account) {
                            $accountId = $account['id'];
                            $accountName = $account['name'];
                            
                            try {
                                // 1. BUSCAR CAMPANHAS COM PAGINAÇÃO
                                $campaignsUrl = "https://graph.facebook.com/v21.0/{$accountId}/campaigns?fields=id,name,status,objective,daily_budget,lifetime_budget,bid_strategy,buying_type,start_time,stop_time,effective_status,created_time,updated_time&limit=100&access_token=" . $accessToken;
                                $allCampaignsData = ['data' => []];
                                
                                do {
                                    $campaignsData = makeAPICall($campaignsUrl);
                                    if (isset($campaignsData['data'])) {
                                        $allCampaignsData['data'] = array_merge($allCampaignsData['data'], $campaignsData['data']);
                                    }
                                    $campaignsUrl = $campaignsData['paging']['next'] ?? null;
                                } while ($campaignsUrl);
                                
                                $campaignStmt = $pdo->prepare("
                                    INSERT INTO meta_campaigns (campaign_id, account_id, name, status, objective, created_time, updated_time)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE 
                                        name = VALUES(name), status = VALUES(status), updated_time = VALUES(updated_time), last_sync = NOW()
                                ");
                                
                                foreach ($allCampaignsData['data'] ?? [] as $campaign) {
                                    $campaignStmt->execute([
                                        $campaign['id'], $accountId, $campaign['name'] ?? '', $campaign['status'] ?? '',
                                        $campaign['objective'] ?? '', $campaign['created_time'] ?? null, $campaign['updated_time'] ?? null
                                    ]);
                                    $totalSynced['campaigns']++;
                                }
                                
                                // 2. BUSCAR AD SETS COM PAGINAÇÃO
                                $adsetsUrl = "https://graph.facebook.com/v21.0/{$accountId}/adsets?fields=id,name,status,campaign_id,daily_budget,lifetime_budget,optimization_goal,billing_event,targeting,start_time,end_time,created_time,updated_time&limit=100&access_token=" . $accessToken;
                                $allAdsetsData = ['data' => []];
                                
                                do {
                                    $adsetsData = makeAPICall($adsetsUrl);
                                    if (isset($adsetsData['data'])) {
                                        $allAdsetsData['data'] = array_merge($allAdsetsData['data'], $adsetsData['data']);
                                    }
                                    $adsetsUrl = $adsetsData['paging']['next'] ?? null;
                                } while ($adsetsUrl);
                                
                                $adsetStmt = $pdo->prepare("
                                    INSERT INTO meta_adsets (adset_id, campaign_id, account_id, name, status, targeting_json, age_min, age_max, created_time, updated_time)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE 
                                        name = VALUES(name), status = VALUES(status), updated_time = VALUES(updated_time), last_sync = NOW()
                                ");
                                
                                foreach ($allAdsetsData['data'] ?? [] as $adset) {
                                    $targeting = $adset['targeting'] ?? [];
                                    $adsetStmt->execute([
                                        $adset['id'], $adset['campaign_id'] ?? '', $accountId, $adset['name'] ?? '', $adset['status'] ?? '',
                                        json_encode($targeting), $targeting['age_min'] ?? null, $targeting['age_max'] ?? null,
                                        $adset['created_time'] ?? null, $adset['updated_time'] ?? null
                                    ]);
                                    $totalSynced['adsets']++;
                                }
                                
                                // 3. BUSCAR ANÚNCIOS + CRIATIVOS COM PAGINAÇÃO
                                $adsUrl = "https://graph.facebook.com/v21.0/{$accountId}/ads?fields=id,name,status,campaign_id,adset_id,effective_status,configured_status,tracking_specs,creative,preview_shareable_link,created_time,updated_time&limit=100&access_token=" . $accessToken;
                                $allAdsData = ['data' => []];
                                
                                // Fazer paginação para evitar erro 500
                                do {
                                    $adsData = makeAPICall($adsUrl);
                                    if (isset($adsData['data'])) {
                                        $allAdsData['data'] = array_merge($allAdsData['data'], $adsData['data']);
                                    }
                                    $adsUrl = $adsData['paging']['next'] ?? null;
                                } while ($adsUrl);
                                
                                $adStmt = $pdo->prepare("
                                    INSERT INTO meta_ads (ad_id, campaign_id, adset_id, account_id, name, status, preview_url, created_time, updated_time)
                                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE 
                                        name = VALUES(name), status = VALUES(status), updated_time = VALUES(updated_time), last_sync = NOW()
                                ");
                                
                                $creativeStmt = $pdo->prepare("
                                    INSERT INTO meta_creatives (creative_id, ad_id, title, body, call_to_action, image_url, video_url)
                                    VALUES (?, ?, ?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE 
                                        title = VALUES(title), body = VALUES(body), last_sync = NOW()
                                ");
                                
                                foreach ($allAdsData['data'] ?? [] as $ad) {
                                    // Inserir anúncio
                                    $adStmt->execute([
                                        $ad['id'], $ad['campaign_id'] ?? '', $ad['adset_id'] ?? '', $accountId, $ad['name'] ?? '',
                                        $ad['status'] ?? '', $ad['preview_shareable_link'] ?? null,
                                        $ad['created_time'] ?? null, $ad['updated_time'] ?? null
                                    ]);
                                    $totalSynced['ads']++;
                                    
                                    // Buscar detalhes do criativo
                                    if (isset($ad['creative']['id'])) {
                                        $creativeUrl = "https://graph.facebook.com/v21.0/{$ad['creative']['id']}?fields=title,body,call_to_action_type,image_url,video_id&access_token=" . $accessToken;
                                        $creativeData = makeAPICall($creativeUrl);
                                        
                                        $creativeStmt->execute([
                                            $ad['creative']['id'], $ad['id'], $creativeData['title'] ?? '',
                                            $creativeData['body'] ?? '', $creativeData['call_to_action_type'] ?? '',
                                            $creativeData['image_url'] ?? null, $creativeData['video_id'] ?? null
                                        ]);
                                        $totalSynced['creatives']++;
                                    }
                                }
                                
                                // 4. BUSCAR MÉTRICAS DETALHADAS
                                $insightsUrl = "https://graph.facebook.com/v21.0/{$accountId}/insights?fields=campaign_id,adset_id,ad_id,impressions,clicks,spend,reach,frequency,cpc,cpm,ctr,conversions,cost_per_result,unique_clicks,inline_link_clicks,outbound_clicks,actions&level=ad&limit=1000&access_token=" . $accessToken;
                                $insightsData = makeAPICall($insightsUrl);
                                
                                $metricsStmt = $pdo->prepare("
                                    UPDATE meta_ads SET 
                                        spend = ?, impressions = ?, clicks = ?, reach = ?, frequency = ?,
                                        cpc = ?, cpm = ?, ctr = ?, conversions = ?, last_sync = NOW()
                                    WHERE ad_id = ?
                                ");
                                
                                foreach ($insightsData['data'] ?? [] as $insight) {
                                    $conversions = 0;
                                    if (isset($insight['actions'])) {
                                        foreach ($insight['actions'] as $action) {
                                            if ($action['action_type'] === 'lead') {
                                                $conversions = (int)$action['value'];
                                                break;
                                            }
                                        }
                                    }
                                    
                                    $metricsStmt->execute([
                                        (float)($insight['spend'] ?? 0), (int)($insight['impressions'] ?? 0),
                                        (int)($insight['clicks'] ?? 0), (int)($insight['reach'] ?? 0),
                                        (float)($insight['frequency'] ?? 0), (float)($insight['cpc'] ?? 0),
                                        (float)($insight['cpm'] ?? 0), (float)($insight['ctr'] ?? 0),
                                        $conversions, $insight['ad_id']
                                    ]);
                                }
                                
                            } catch (Exception $e) {
                                $errors[] = "Conta {$accountName}: " . $e->getMessage();
                            }
                        }
                        
                        echo json_encode([
                            'success' => true,
                            'message' => 'Sincronização COMPLETA do Meta Ads concluída!',
                            'synced' => $totalSynced,
                            'total_items' => array_sum($totalSynced),
                            'processed_accounts' => count($accountsData['data']),
                            'errors' => $errors
                        ]);
                        break;
                }
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        elseif ($action === 'sync') {
            header('Content-Type: application/json; charset=utf-8');
            
            $page = (int)($_POST['page'] ?? 1);
            $total_synced = (int)($_POST['total_synced'] ?? 0);
            
            $url = "https://{$KOMMO_SUBDOMAIN}.kommo.com/api/v4/leads?page=$page&limit=250&with=contacts";
            $response = callAPI($url, $KOMMO_ACCESS_TOKEN);
            
            $leads = $response['_embedded']['leads'] ?? [];
            $total_leads = $response['_page']['total'] ?? 0;
            
            if (empty($leads)) {
                echo json_encode([
                    'finished' => true,
                    'total_synced' => $total_synced,
                    'total_leads' => $total_leads,
                    'batch_synced' => 0,
                    'message' => 'Sem leads para processar nesta página'
                ]);
                exit;
            }
            
            $stmt = $pdo->prepare("INSERT INTO leads (kommo_id, nome, price, responsible_user_id, group_id, status_id, pipeline_id, loss_reason_id, created_by, updated_by, created_at, updated_at, closed_at, closest_task_at, is_deleted, equipe, trabalhista_qualidade, primeiro_nome, qualidade_lesao, sexo, validacao_audio_zaias, recebeu_inss, profissao, nome_documento, nacionalidade_documento, estado_civil_documento, profissao_documento, rg_documento, cpf_documento, rua_documento, numero_documento, bairro_documento, cidade_documento, estado_documento, cep_documento, telefone, data_acidente, closer, sdr, prof_epoca, tipo_processo, tipo_acidente, lesao, cep_cidade_cliente, estado, origem, id_ad, data_da_venda, data_nascimento, perito, data_pericia_aux_acid, endereco_pericia_aux, prof_atual, assinatura_procuracao, data_protocolo, cep_comarca, id_anuncio, pericia, pericia_realizada, prop_de_acordo, status_processo, transito_em_julgado, numero_processo, tribunal, data_fim_aux_doenca, data_pgto_bi, r_liquido_bi, arquivado, cpf, tipo_pgto, nota_fiscal, percent_contrato, salarios_minimos, valor_atrasados_total, parte_cliente, recibo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE nome = VALUES(nome), price = VALUES(price), status_id = VALUES(status_id), updated_at = VALUES(updated_at)");
            
            $synced = 0;
            $pdo->beginTransaction();
            
            foreach ($leads as $lead) {
                try {
                    $fields = $lead['custom_fields_values'] ?? [];
                    
                    // Mapear campos
                    $campos = [];
                    foreach ($fields as $field) {
                        $name = $field['field_name'] ?? '';
                        $value = $field['values'][0]['value'] ?? '';
                        
                        if ($name === 'CPF' && is_array($value)) {
                            $campos[$name] = $value['name'] ?? '';
                        } elseif ($name === 'CEP/Cidade Cliente' && is_array($value)) {
                            $campos[$name] = json_encode($value);
                        } else {
                            $campos[$name] = $value;
                        }
                    }
                    
                    $values = [
                        $lead['id'], $lead['name'] ?? '', $lead['price'] ?? null, $lead['responsible_user_id'] ?? null, $lead['group_id'] ?? null, $lead['status_id'] ?? null, $lead['pipeline_id'] ?? null, $lead['loss_reason_id'] ?? null, $lead['created_by'] ?? null, $lead['updated_by'] ?? null, toDateTime($lead['created_at'] ?? null), toDateTime($lead['updated_at'] ?? null), toDateTime($lead['closed_at'] ?? null), toDateTime($lead['closest_task_at'] ?? null), $lead['is_deleted'] ?? false,
                        $campos['Equipe'] ?? '', $campos['Trabalhista Qualidade'] ?? '', $campos['1º Nome'] ?? '', $campos['Qualidade Lesão'] ?? '', $campos['Sexo'] ?? '', $campos['Validação Áudio Zaias'] ?? '', $campos['Recebeu INSS'] ?? '', $campos['Profissão'] ?? '', $campos['Nome Documento'] ?? '', $campos['Nacionalidade Documento'] ?? '', $campos['Estado Civil Documento'] ?? '', $campos['Profissão Documento'] ?? '', $campos['Rg Documento'] ?? '', $campos['CPF Documento'] ?? '', $campos['Rua Documento'] ?? '', $campos['Número Documento'] ?? '', $campos['Bairro Documento'] ?? '', $campos['Cidade Documento'] ?? '', $campos['Estado Documento'] ?? '', $campos['Cep Documento'] ?? '', $campos['Telefone'] ?? '', toDate($campos['Data Acidente'] ?? null), $campos['Closer'] ?? '', $campos['SDR'] ?? '', $campos['Prof. Época'] ?? '', $campos['Tipo Processo'] ?? '', $campos['Tipo Acidente'] ?? '', $campos['Lesão'] ?? '', $campos['CEP/Cidade Cliente'] ?? '', $campos['Estado'] ?? '', $campos['Origem'] ?? '', $campos['ID Ad'] ?? '', toDate($campos['Data da Venda'] ?? null), toDate($campos['Data de nascimento'] ?? null), $campos['Perito'] ?? '', toDateTime($campos['Data Perícia Aux Acid'] ?? null), $campos['Endereço Perícia Aux'] ?? '', $campos['Prof. Atual'] ?? '', toDate($campos['Assinatura Procuração'] ?? null), toDate($campos['Data Protocolo'] ?? null), $campos['CEP Comarca'] ?? '', $campos['ID Anuncio'] ?? '', $campos['Perícia'] ?? '', $campos['Perícia Realizada?'] ?? '', $campos['Prop. de Acordo'] ?? '', $campos['Status Processo'] ?? '', toDate($campos['Trânsito em Julgado'] ?? null), $campos['Nº Processo'] ?? '', $campos['Tribunal'] ?? '', toDate($campos['Data Fim Aux. Doença'] ?? null), toDate($campos['Data Pgto BI'] ?? null), $campos['R$ Líquido BI'] ?? null, $campos['Arquivado'] ?? '', $campos['CPF'] ?? '', $campos['Tipo Pgto'] ?? '', $campos['Nota Fiscal'] ?? '', $campos['% do Contrato'] ?? '', $campos['Salários Mínimos'] ?? null, $campos['Valor Atrasados Total R$'] ?? '', $campos['Parte Cliente'] ?? '', ($campos['Recibo'] ?? false) ? 1 : 0
                    ];
                    
                    if ($stmt->execute($values)) {
                        $synced++;
                    }
                } catch (Exception $e) {
                    error_log("Erro lead {$lead['id']}: " . $e->getMessage());
                }
            }
            
            $pdo->commit();
            $total_synced += $synced;
            $has_next = count($leads) === 250;
            
            echo json_encode([
                'finished' => !$has_next,
                'page' => $page + 1,
                'total_synced' => $total_synced,
                'total_leads' => $total_leads,
                'batch_synced' => $synced
            ]);
            exit;
        }
        
        elseif ($action === 'api4com_test') {
            header('Content-Type: application/json; charset=utf-8');
            
            $testType = $_POST['test_type'] ?? 'connection';
            $apiKey = $_ENV['API4COM_API_KEY'] ?? '';
            
            try {
                switch ($testType) {
                    case 'connection':
                        $url = "https://api.api4com.com/api/v1/calls?page=1&limit=1";
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: ' . $apiKey,
                            'Content-Type: application/json'
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode === 200) {
                            $data = json_decode($response, true);
                            echo json_encode([
                                'success' => true,
                                'message' => 'Conexão Api4com OK',
                                'calls_count' => count($data['data'] ?? []),
                                'api_response' => $data
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'error' => "HTTP $httpCode: $response"
                            ]);
                        }
                        break;
                        
                    case 'get_calls':
                        $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
                        $dateTo = $_POST['date_to'] ?? date('Y-m-d');
                        
                        $url = "https://api.api4com.com/api/v1/calls?page=1&limit=50";
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: ' . $apiKey,
                            'Content-Type: application/json'
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode === 200) {
                            $data = json_decode($response, true);
                            echo json_encode([
                                'success' => true,
                                'message' => "Encontradas " . count($data['data'] ?? []) . " chamadas",
                                'calls' => $data['data'] ?? [],
                                'period' => "{$dateFrom} a {$dateTo}"
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'error' => "HTTP $httpCode: $response"
                            ]);
                        }
                        break;
                        
                    case 'sync_calls':
                        $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
                        $dateTo = $_POST['date_to'] ?? date('Y-m-d');
                        $page = (int)($_POST['page'] ?? 1);
                        $limit = 100;
                        
                        $url = "https://api.api4com.com/api/v1/calls?page={$page}&limit={$limit}";
                        
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, [
                            'Authorization: ' . $apiKey,
                            'Content-Type: application/json'
                        ]);
                        
                        $response = curl_exec($ch);
                        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);
                        
                        if ($httpCode !== 200) {
                            echo json_encode([
                                'success' => false,
                                'error' => "HTTP $httpCode: $response"
                            ]);
                            exit;
                        }
                        
                        $data = json_decode($response, true);
                        $calls = $data['data'] ?? [];
                        
                        if (empty($calls)) {
                            echo json_encode([
                                'finished' => true,
                                'message' => 'Nenhuma chamada encontrada para sincronizar',
                                'synced' => 0
                            ]);
                            exit;
                        }
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO chamadas_api4com (
                                id_chamada, domain, direction, caller, called, started_at, 
                                answered_at, ended_at, duration, hangup_cause, hangup_cause_code, 
                                record_url, metadata_json, email, first_name, last_name, bina, 
                                minute_price, call_price, created_at, updated_at
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE
                                domain = VALUES(domain),
                                direction = VALUES(direction),
                                caller = VALUES(caller),
                                called = VALUES(called),
                                started_at = VALUES(started_at),
                                answered_at = VALUES(answered_at),
                                ended_at = VALUES(ended_at),
                                duration = VALUES(duration),
                                hangup_cause = VALUES(hangup_cause),
                                hangup_cause_code = VALUES(hangup_cause_code),
                                record_url = VALUES(record_url),
                                metadata_json = VALUES(metadata_json),
                                email = VALUES(email),
                                first_name = VALUES(first_name),
                                last_name = VALUES(last_name),
                                bina = VALUES(bina),
                                minute_price = VALUES(minute_price),
                                call_price = VALUES(call_price),
                                updated_at = NOW()
                        ");
                        
                        $synced = 0;
                        $pdo->beginTransaction();
                        
                        foreach ($calls as $call) {
                            try {
                                $values = [
                                    $call['id'] ?? '',
                                    $call['domain'] ?? '',
                                    $call['call_type'] ?? 'outbound',
                                    $call['from'] ?? '',
                                    $call['to'] ?? '',
                                    $call['started_at'] ?? null,
                                    null, // answered_at (não disponível na API)
                                    $call['ended_at'] ?? null,
                                    (int)($call['duration'] ?? 0),
                                    $call['hangup_cause'] ?? '',
                                    0, // hangup_cause_code (não disponível na API)
                                    $call['record_url'] ?? '',
                                    json_encode($call['metadata'] ?? []),
                                    $call['email'] ?? '',
                                    $call['first_name'] ?? '',
                                    $call['last_name'] ?? '',
                                    $call['BINA'] ?? '',
                                    $call['minute_price'] ? (float)$call['minute_price'] : null,
                                    $call['call_price'] ? (float)$call['call_price'] : null
                                ];
                                
                                if ($stmt->execute($values)) {
                                    $synced++;
                                }
                            } catch (Exception $e) {
                                error_log("Erro chamada {$call['id']}: " . $e->getMessage());
                            }
                        }
                        
                        $pdo->commit();
                        
                        $hasMore = count($calls) >= $limit;
                        
                        echo json_encode([
                            'success' => true,
                            'synced' => $synced,
                            'total_calls' => count($calls),
                            'has_more' => $hasMore,
                            'next_page' => $hasMore ? $page + 1 : null,
                            'finished' => !$hasMore,
                            'message' => "Sincronizadas {$synced} chamadas da página {$page}"
                        ]);
                        break;
                }
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        // Debug endpoint para testar requisições
        elseif ($action === 'debug_post') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => true,
                'message' => 'API funcionando!',
                'method' => $_SERVER['REQUEST_METHOD'],
                'post_data' => $_POST,
                'get_data' => $_GET,
                'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'not set'
            ]);
            exit;
        }
        
        // API para controle de sincronização em background
        elseif ($action === 'start_background_sync') {
            header('Content-Type: application/json; charset=utf-8');
            
            try {
                $syncType = $_POST['type'] ?? $_GET['type'] ?? 'all'; // all, kommo, meta, api4com
                $jobId = uniqid('sync_', true);
                
                // Salvar status inicial
                $statusFile = __DIR__ . "/sync_status_{$jobId}.json";
                $status = [
                    'job_id' => $jobId,
                    'type' => $syncType,
                    'status' => 'starting',
                    'started_at' => date('Y-m-d H:i:s'),
                    'progress' => 0,
                    'total_steps' => 0,
                    'current_step' => 0,
                    'current_task' => 'Iniciando...',
                    'logs' => [],
                    'results' => []
                ];
                file_put_contents($statusFile, json_encode($status, JSON_PRETTY_PRINT));
                
                // Como exec() está desabilitado, vamos simular o background
                // Criar arquivo de job para processamento via cron ou chamada manual
                $jobFile = __DIR__ . "/job_{$jobId}.json";
                $jobData = [
                    'job_id' => $jobId,
                    'type' => $syncType,
                    'created_at' => date('Y-m-d H:i:s'),
                    'status' => 'queued'
                ];
                file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT));
                
                // Atualizar status para processing
                updateStatus($statusFile, [
                    'status' => 'processing',
                    'current_task' => 'Processando em background simulado...'
                ]);
                
                // Iniciar processamento via cURL em background para evitar timeout
                $processUrl = "https://bmzdashboard.shop/Sincronizacao/index.php?action=process_job&job_id=$jobId";
                
                // EXECUÇÃO DIRETA - Como o exec() está desabilitado, executar direto
                // ao invés de tentar cURL assíncrono que não funciona no Hostinger
                
                // Atualizar status para running
                updateStatus($statusFile, [
                    'status' => 'running',
                    'current_task' => 'Processando sincronização...'
                ]);
                
                // Processar o job DIRETAMENTE (sem cURL)
                try {
                    performBackgroundSync($jobId, $syncType);
                } catch (Exception $e) {
                    updateStatus($statusFile, [
                        'status' => 'error',
                        'current_task' => 'Erro durante processamento',
                        'error' => $e->getMessage()
                    ]);
                    addLog($statusFile, "ERRO: " . $e->getMessage());
                }
                
                echo json_encode([
                    'success' => true,
                    'job_id' => $jobId,
                    'message' => "Sincronização iniciada em background",
                    'status_url' => "?action=get_sync_status&job_id=$jobId",
                    'process_url' => $processUrl
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        elseif ($action === 'get_sync_status') {
            header('Content-Type: application/json; charset=utf-8');
            
            try {
                $jobId = $_GET['job_id'] ?? '';
                if (empty($jobId)) {
                    throw new Exception('Job ID é obrigatório');
                }
                
                $statusFile = __DIR__ . "/sync_status_{$jobId}.json";
                
                if (!file_exists($statusFile)) {
                    throw new Exception('Job não encontrado');
                }
                
                $status = json_decode(file_get_contents($statusFile), true);
                
                echo json_encode([
                    'success' => true,
                    'status' => $status
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        elseif ($action === 'list_sync_jobs') {
            header('Content-Type: application/json; charset=utf-8');
            
            try {
                $jobs = [];
                $files = glob(__DIR__ . "/sync_status_*.json");
                
                foreach ($files as $file) {
                    $status = json_decode(file_get_contents($file), true);
                    if ($status) {
                        $jobs[] = [
                            'job_id' => $status['job_id'],
                            'type' => $status['type'],
                            'status' => $status['status'],
                            'started_at' => $status['started_at'],
                            'progress' => $status['progress'],
                            'current_task' => $status['current_task']
                        ];
                    }
                }
                
                // Ordenar por data de início (mais recente primeiro)
                usort($jobs, function($a, $b) {
                    return strtotime($b['started_at']) - strtotime($a['started_at']);
                });
                
                echo json_encode([
                    'success' => true,
                    'jobs' => $jobs
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        elseif ($action === 'stop_sync_job') {
            header('Content-Type: application/json; charset=utf-8');
            
            try {
                $jobId = $_POST['job_id'] ?? $_GET['job_id'] ?? '';
                if (empty($jobId)) {
                    throw new Exception('Job ID é obrigatório');
                }
                
                $statusFile = __DIR__ . "/sync_status_{$jobId}.json";
                
                if (!file_exists($statusFile)) {
                    throw new Exception('Job não encontrado');
                }
                
                $status = json_decode(file_get_contents($statusFile), true);
                
                if ($status['status'] === 'completed') {
                    throw new Exception('Job já foi concluído');
                }
                
                if ($status['status'] === 'stopped') {
                    throw new Exception('Job já foi parado');
                }
                
                // Criar arquivo de stop
                $stopFile = __DIR__ . "/stop_{$jobId}.flag";
                file_put_contents($stopFile, date('Y-m-d H:i:s'));
                
                // Atualizar status
                updateStatus($statusFile, [
                    'status' => 'stopped',
                    'current_task' => 'Sincronização interrompida pelo usuário',
                    'stopped_at' => date('Y-m-d H:i:s')
                ]);
                
                addLog($statusFile, 'SINCRONIZAÇÃO INTERROMPIDA PELO USUÁRIO');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Sincronização interrompida com sucesso',
                    'job_id' => $jobId
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        elseif ($action === 'stop_all_jobs') {
            header('Content-Type: application/json; charset=utf-8');
            
            try {
                $stoppedJobs = [];
                $files = glob(__DIR__ . "/sync_status_*.json");
                
                foreach ($files as $file) {
                    $status = json_decode(file_get_contents($file), true);
                    if ($status && $status['status'] === 'running') {
                        $jobId = $status['job_id'];
                        
                        // Criar arquivo de stop
                        $stopFile = __DIR__ . "/stop_{$jobId}.flag";
                        file_put_contents($stopFile, date('Y-m-d H:i:s'));
                        
                        // Atualizar status
                        updateStatus($file, [
                            'status' => 'stopped',
                            'current_task' => 'Sincronização interrompida pelo usuário',
                            'stopped_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        addLog($file, 'SINCRONIZAÇÃO INTERROMPIDA PELO USUÁRIO');
                        $stoppedJobs[] = $jobId;
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Todas as sincronizações foram interrompidas',
                    'stopped_jobs' => $stoppedJobs,
                    'count' => count($stoppedJobs)
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
        elseif ($action === 'process_job') {
            header('Content-Type: application/json; charset=utf-8');
            
            try {
                $jobId = $_POST['job_id'] ?? $_GET['job_id'] ?? '';
                if (empty($jobId)) {
                    throw new Exception('Job ID é obrigatório');
                }
                
                $statusFile = __DIR__ . "/sync_status_{$jobId}.json";
                
                if (!file_exists($statusFile)) {
                    throw new Exception('Job não encontrado');
                }
                
                $status = json_decode(file_get_contents($statusFile), true);
                
                if (!in_array($status['status'], ['processing', 'queued'])) {
                    throw new Exception('Job não está em estado válido para processamento: ' . $status['status']);
                }
                
                // Processar o job
                $syncType = $status['type'];
                processBackgroundJob($jobId, $syncType, $statusFile, $pdo ?? null);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Job processado com sucesso',
                    'job_id' => $jobId
                ]);
                
            } catch (Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => $e->getMessage()
                ]);
            }
            exit;
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) $pdo->rollback();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Stats iniciais
try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $current = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
} catch (Exception $e) {
    $current = 0;
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>🚀 Sincronização KOMMO → MySQL - COMPLETA</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; max-width: 1000px; margin: 0 auto; }
        h1 { color: #333; text-align: center; margin-bottom: 30px; }
        .tabs { display: flex; border-bottom: 2px solid #ddd; margin-bottom: 30px; }
        .tab { padding: 15px 25px; cursor: pointer; border: none; background: #f8f9fa; margin-right: 5px; border-radius: 5px 5px 0 0; }
        .tab.active { background: #007bff; color: white; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .stat { background: #e3f2fd; padding: 20px; margin: 20px 0; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; color: #1976d2; }
        .btn { background: #4caf50; color: white; padding: 15px 30px; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; margin: 5px; }
        .btn:disabled { background: #ccc; }
        .btn-danger { background: #dc3545; }
        .btn-warning { background: #ffc107; color: #212529; }
        .btn-info { background: #17a2b8; }
        .progress { margin: 20px 0; height: 30px; background: #e0e0e0; border-radius: 15px; overflow: hidden; }
        .progress-bar { height: 100%; background: #4caf50; width: 0%; transition: width 0.3s; }
        .log { background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px; height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; }
        .error { color: red; font-weight: bold; }
        .success { color: green; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #f8f9fa; padding: 20px; border-radius: 8px; border-left: 4px solid #007bff; }
        .json-viewer { background: #2d3748; color: #e2e8f0; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; max-height: 400px; overflow: auto; }
        .input-group { margin: 15px 0; }
        .input-group input { padding: 10px; border: 1px solid #ddd; border-radius: 4px; width: 200px; margin-right: 10px; }
        .auto-sync-status { padding: 15px; margin: 20px 0; border-radius: 8px; text-align: center; }
        .auto-sync-status.active { background: #d4edda; color: #155724; border: 2px solid #c3e6cb; }
        .auto-sync-status.inactive { background: #f8d7da; color: #721c24; border: 2px solid #f5c6cb; }
        .countdown { font-size: 1.2em; font-weight: bold; color: #007bff; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Sincronização dados BMZ → Banco de dados SQL</h1>
        <p style="text-align: center; color: #666;">Sincronização completa de Leads, Facebook e Api4com</p>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('sync')">🚀 Kommo Sync</button>
            <button class="tab" onclick="showTab('meta')">📊 Meta Ads</button>
            <button class="tab" onclick="showTab('api4com')">📞 Api4com</button>
            <button class="tab" onclick="showTab('complete')">⚡ Sincronizar Tudo</button>
            <button class="tab" onclick="showTab('test')">🧪 Teste Lead</button>
            <button class="tab" onclick="showTab('debug')">🔍 Debug</button>
        </div>
        
        <!-- TAB: Sincronização -->
        <div id="sync" class="tab-content active">
            <?php if (isset($error)): ?>
            <div style="background: #ffebee; color: #c62828; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                ❌ <strong>Erro:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <div class="stat">
                <div class="stat-number" id="current"><?php echo number_format($current); ?></div>
                <div>Leads no Banco</div>
            </div>
            
            <div class="grid">
                <div class="stat">
                    <div class="stat-number" id="total">-</div>
                    <div>Total API</div>
                </div>
                <div class="stat">
                    <div class="stat-number" id="synced">0</div>
                    <div>Sincronizados</div>
                </div>
            </div>
            
            
            <button class="btn" id="syncBtn" onclick="startSync()" <?php echo isset($error) ? 'disabled' : ''; ?>>
                🚀 Sincronizar TODOS (250/página)
            </button>
            
            <div class="progress" style="display: none;" id="progress">
                <div class="progress-bar" id="progressBar"></div>
            </div>
            <div id="status" style="text-align: center; margin-top: 10px;"></div>
            
            <div class="log" id="syncLog" style="display: none;"></div>
        </div>
        
        <!-- TAB: Teste Lead -->
        <div id="test" class="tab-content">
            <div class="card">
                <h3>🧪 Teste de Lead Específico</h3>
                <p>Teste a inserção de um lead específico com debug completo</p>
                
                <div class="input-group">
                    <input type="number" id="leadId" placeholder="ID do Lead" value="13846830">
                    <button class="btn btn-info" onclick="testLead()">🧪 Testar Lead</button>
                </div>
                
                <div id="testResult" class="log" style="display: none;"></div>
            </div>
        </div>
        
        <!-- TAB: Meta Ads -->
        <div id="meta" class="tab-content">
            <div class="card">
                <h3>📊 Meta Ads (Facebook) - Teste de API</h3>
                <p>Testar conexão e ver dados retornados pelo Meta Ads</p>
                
                <div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4>🔑 Credenciais Encontradas no .env:</h4>
                    <p><strong>App ID:</strong> <?php echo $_ENV['FACEBOOK_APP_ID'] ?? 'Não encontrado'; ?></p>
                    <p><strong>Access Token:</strong> <?php echo substr($_ENV['FACEBOOK_ACCESS_TOKEN'] ?? 'Não encontrado', 0, 50) . '...'; ?></p>
                    <p><strong>Contas Ads:</strong> 
                        <?php 
                        $accounts = [];
                        for($i = 1; $i <= 6; $i++) {
                            $key = "BMZ_ACCOUNT_0$i";
                            if(isset($_ENV[$key])) $accounts[] = $_ENV[$key];
                        }
                        echo implode(', ', $accounts);
                        ?>
                    </p>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button class="btn btn-info" onclick="testMetaConnection()">🔍 Testar Conexão</button>
                    <button class="btn btn-success" onclick="getCampaigns()">📊 Buscar Campanhas</button>
                    <button class="btn btn-warning" onclick="getAdSets()">🎯 Buscar Ad Sets</button>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button class="btn btn-info" onclick="getAds()">📱 Buscar Anúncios</button>
                    <button class="btn btn-success" onclick="getInsights()">📈 Buscar Métricas</button>
                </div>
                
                <div style="text-align: center; margin: 20px 0; padding: 15px; background: #e8f5e8; border-radius: 5px;">
                    <h4>🔄 Sincronização com Banco de Dados</h4>
                    <p>Sincronizar dados do Meta Ads para o MySQL (últimos 30 dias)</p>
                    <button class="btn btn-success" onclick="syncMetaToDB()" id="syncMetaBtn">🚀 Sincronizar Meta Ads → MySQL</button>
                </div>
            </div>
            
            <div class="log" id="metaLog" style="display: none; height: 400px;"></div>
        </div>
        
        <!-- TAB: Api4com -->
        <div id="api4com" class="tab-content">
            <div class="card">
                <h3>📞 Api4com - Sincronização de Chamadas</h3>
                <p>Sincronizar dados de chamadas da Api4com com o banco de dados</p>
                
                <div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4>🔑 Configurações Necessárias no .env:</h4>
                    <p><strong>API Key:</strong> <?php echo isset($_ENV['API4COM_API_KEY']) ? substr($_ENV['API4COM_API_KEY'], 0, 20) . '...' : '❌ Não encontrado'; ?></p>
                    <div style="background: #fff3cd; padding: 10px; border-radius: 3px; margin-top: 10px;">
                        <small>📝 <strong>Adicione no .env:</strong><br>
                        API4COM_API_KEY=seu_token_aqui</small>
                    </div>
                </div>
                
                <div style="text-align: center; margin: 20px 0;">
                    <button class="btn btn-info" onclick="testApi4comConnection()">🔍 Testar Conexão</button>
                    <button class="btn btn-success" onclick="getApi4comCalls()">📞 Buscar Chamadas (7 dias)</button>
                </div>
                
                <div style="margin: 20px 0;">
                    <h4>📅 Período para Sincronização</h4>
                    <div style="display: flex; gap: 10px; align-items: center; justify-content: center;">
                        <label>De:</label>
                        <input type="date" id="api4com_date_from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                        <label>Até:</label>
                        <input type="date" id="api4com_date_to" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                
                <div style="text-align: center; margin: 20px 0; padding: 15px; background: #e8f5e8; border-radius: 5px;">
                    <h4>🔄 Sincronização com Banco de Dados</h4>
                    <p>Sincronizar chamadas da Api4com para MySQL</p>
                    <button class="btn btn-success" onclick="syncApi4comToDB()" id="syncApi4comBtn">🚀 Sincronizar Api4com → MySQL</button>
                    <div class="progress" style="display: none; margin-top: 10px;" id="api4comProgress">
                        <div class="progress-bar" id="api4comProgressBar"></div>
                    </div>
                    <div id="api4comStatus" style="margin-top: 10px;"></div>
                </div>
            </div>
            
            <div class="log" id="api4comLog" style="display: none; height: 400px;"></div>
        </div>
        
        <!-- TAB: Sincronização Completa -->
        <div id="complete" class="tab-content">
            <div class="card">
                <h3>⚡ Sincronização Completa BMZ</h3>
                <p>Sincronizar todas as fontes de dados simultaneamente</p>
                
                <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 15px 0;">
                    <h4>🔄 O que será sincronizado:</h4>
                    <p>✅ <strong>Leads do Kommo:</strong> Todos os leads e campos personalizados</p>
                    <p>✅ <strong>Meta Ads (Facebook):</strong> Campanhas, Ad Sets, Anúncios e Métricas</p>
                    <p>✅ <strong>Api4com:</strong> Todas as chamadas com dados completos</p>
                </div>
                
                <div style="text-align: center; margin: 30px 0;">
                    <button class="btn btn-success" onclick="startCompleteSync()" id="completeSyncBtn" style="font-size: 18px; padding: 15px 30px;">
                        🚀 SINCRONIZAR TUDO
                    </button>
                </div>
                
                <div class="progress" style="display: none; margin-top: 20px;" id="completeProgress">
                    <div class="progress-bar" id="completeProgressBar"></div>
                </div>
                
                <div id="completeStatus" style="text-align: center; margin-top: 15px; font-weight: bold;"></div>
                
                <!-- Status individual de cada sincronização -->
                <div class="grid" id="syncStatusGrid" style="display: none; margin-top: 20px;">
                    <div class="card">
                        <h4>🚀 Kommo Leads</h4>
                        <div id="kommoStatus">⏳ Aguardando...</div>
                        <div id="kommoProgress">0 leads sincronizados</div>
                    </div>
                    
                    <div class="card">
                        <h4>📊 Meta Ads</h4>
                        <div id="metaStatus">⏳ Aguardando...</div>
                        <div id="metaProgress">0 campanhas sincronizadas</div>
                    </div>
                    
                    <div class="card">
                        <h4>📞 Api4com</h4>
                        <div id="api4comStatus">⏳ Aguardando...</div>
                        <div id="api4comProgress">0 chamadas sincronizadas</div>
                    </div>
                </div>
            </div>
            
            <div class="log" id="completeLog" style="display: none; height: 400px;"></div>
        </div>
        
        <!-- TAB: Logs -->
        <div id="logs" class="tab-content">
            <div class="card">
                <h3>📋 Logs do Sistema</h3>
                <p>Acompanhe todas as atividades de sincronização em tempo real</p>
                <p><strong>⏰ Sistema configurado para 5 MINUTOS (teste)</strong></p>
                <p id="lastCheck">🔍 Última verificação: Carregando...</p>
                
                <div style="text-align: center; margin: 15px 0;">
                    <button class="btn btn-info" onclick="clearLogs()">🗑️ Limpar Logs</button>
                    <button class="btn btn-warning" onclick="downloadLogs()">💾 Download Logs</button>
                    <button class="btn btn-success" onclick="checkAutoSync()">🔍 Verificar Agora</button>
                </div>
            </div>
            
            <div class="log" id="systemLog" style="display: block; height: 400px;">
                <div class="success">[<?php echo date('H:i:s'); ?>] ✅ Sistema iniciado</div>
                <div>[<?php echo date('H:i:s'); ?>] 📊 Leads no banco: <?php echo number_format($current); ?></div>
                <div>[<?php echo date('H:i:s'); ?>] 🌐 API Domain: <?php echo $KOMMO_SUBDOMAIN; ?>.kommo.com</div>
            </div>
        </div>
        
        <!-- TAB: Debug -->
        <div id="debug" class="tab-content">    
            <div class="grid">
                <div class="card">
                    <h3>🚀 Kommo CRM</h3>
                    <p><strong>Domain:</strong> <?php echo htmlspecialchars($KOMMO_SUBDOMAIN); ?>.kommo.com</p>
                    <p><strong>Token:</strong> <?php echo substr($KOMMO_ACCESS_TOKEN, 0, 30) . '...'; ?></p>
                    <p><strong>Leads no banco:</strong> <?php echo number_format($current); ?></p>
                    <p><strong>Status:</strong> <span style="color: green;">✅ Configurado</span></p>
                </div>
                
                <div class="card">
                    <h3>📊 Meta Ads</h3>
                    <p><strong>App ID:</strong> <?php echo $_ENV['FACEBOOK_APP_ID'] ?? '❌ Não configurado'; ?></p>
                    <p><strong>Token:</strong> <?php echo isset($_ENV['FACEBOOK_ACCESS_TOKEN']) ? substr($_ENV['FACEBOOK_ACCESS_TOKEN'], 0, 30) . '...' : '❌ Não configurado'; ?></p>
                    <p><strong>Campanhas:</strong> <span id="metaCampaignsCount">Carregando...</span></p>
                    <p><strong>Status:</strong> <span id="metaDebugStatus">🔍 Verificando...</span></p>
                </div>
                
                <div class="card">
                    <h3>📞 Api4com</h3>
                    <p><strong>Token:</strong> <?php echo isset($_ENV['API4COM_API_KEY']) ? substr($_ENV['API4COM_API_KEY'], 0, 30) . '...' : '❌ Não configurado'; ?></p>
                    <p><strong>Chamadas:</strong> <span id="api4comCallsCount">Carregando...</span></p>
                    <p><strong>Extensões:</strong> <span id="api4comExtensions">Carregando...</span></p>
                    <p><strong>Status:</strong> <span id="api4comDebugStatus">🔍 Verificando...</span></p>
                </div>
            </div>
            
            <div class="card" style="margin-top: 20px;">
                <h3>🗄️ Informações do Sistema</h3>
                <div class="grid">
                    <div>
                        <p><strong>Database:</strong> <?php echo htmlspecialchars($DB_HOST . '/' . $DB_NAME); ?></p>
                        <p><strong>User:</strong> <?php echo htmlspecialchars($DB_USER); ?></p>
                        <p><strong>Timezone:</strong> <?php echo date_default_timezone_get(); ?></p>
                    </div>
                    <div>
                        <p><strong>Servidor:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
                        <p><strong>PHP:</strong> <?php echo PHP_VERSION; ?></p>
                        <p><strong>MySQL:</strong> <span id="mysqlVersion">Carregando...</span></p>
                    </div>
                </div>
            </div>
            
            <div style="text-align: center; margin: 20px 0;">
                <button class="btn btn-info" onclick="refreshDebugInfo()">🔄 Atualizar Informações</button>
            </div>
            
            <div class="log" id="debugLog">
                <div class="success">✅ Sistema carregado com sucesso</div>
                <div>📊 Todas as sincronizações configuradas e funcionais</div>
                <div>⏰ Última atualização: <?php echo date('Y-m-d H:i:s'); ?></div>
            </div>
        </div>
    </div>

    <script>
        let syncing = false;
        let autoSyncInterval = null;
        let autoSyncEnabled = false;
        let nextAutoSync = null;
        
        function showTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            event.target.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        }
        
        // Função para iniciar daemon de sincronização
        async function startAutoSync() {
            try {
                systemLog('🔄 Tentando iniciar daemon...', '');
                
                const form = new FormData();
                form.append('action', 'daemon_control');
                form.append('cmd', 'start');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.success) {
                    systemLog('🤖 Daemon iniciado! Próxima sync em 5 minutos (TESTE)', 'success');
                    systemLog('✅ Sistema funcionará mesmo com página fechada', 'success');
                    systemLog(`📝 Resposta: ${data.output}`, '');
                    setTimeout(checkDaemonStatus, 2000);
                } else {
                    systemLog('❌ Erro ao iniciar daemon', 'error');
                    systemLog(`📝 Detalhes: ${JSON.stringify(data)}`, 'error');
                }
            } catch (error) {
                systemLog('❌ Erro na requisição: ' + error.message, 'error');
            }
        }
        
        // Função para parar daemon
        async function stopAutoSync() {
            try {
                const form = new FormData();
                form.append('action', 'daemon_control');
                form.append('cmd', 'stop');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.success) {
                    log('🛑 Daemon de sincronização parado', 'warning');
                    updateAutoSyncStatus(false);
                } else {
                    log('❌ Erro ao parar daemon', 'error');
                }
            } catch (error) {
                log('❌ Erro: ' + error.message, 'error');
            }
        }
        
        // Verificar status do daemon
        async function checkDaemonStatus() {
            try {
                const form = new FormData();
                form.append('action', 'daemon_control');
                form.append('cmd', 'status');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.success) {
                    const isActive = data.output.includes('ATIVO');
                    updateAutoSyncStatus(isActive);
                    
                    if (isActive) {
                        // Extrair próxima sincronização do output
                        const lines = data.output.split('\n');
                        const nextLine = lines.find(line => line.includes('Próxima sincronização:'));
                        if (nextLine) {
                            const dateStr = nextLine.split(': ')[1];
                            nextAutoSync = new Date(dateStr);
                        }
                    }
                }
            } catch (error) {
                console.error('Erro ao verificar status:', error);
            }
        }
        
        // Atualizar status da sincronização automática
        function updateAutoSyncStatus(isActive = false) {
            const statusEl = document.getElementById('autoSyncStatus');
            const btnStart = document.getElementById('autoSyncStart');
            const btnStop = document.getElementById('autoSyncStop');
            
            autoSyncEnabled = isActive;
            
            if (isActive && nextAutoSync) {
                statusEl.innerHTML = `🤖 <strong>DAEMON ATIVO</strong><br>Próxima sincronização: ${nextAutoSync.toLocaleString()}<br><small>Funciona mesmo com página fechada</small>`;
                statusEl.className = 'auto-sync-status active';
                btnStart.style.display = 'none';
                btnStop.style.display = 'inline-block';
            } else if (isActive) {
                statusEl.innerHTML = `🤖 <strong>DAEMON ATIVO</strong><br>Sincronizando em background...<br><small>Funciona mesmo com página fechada</small>`;
                statusEl.className = 'auto-sync-status active';
                btnStart.style.display = 'none';
                btnStop.style.display = 'inline-block';
            } else {
                statusEl.innerHTML = '⏸️ <strong>DAEMON DESATIVADO</strong><br>Sincronização manual apenas';
                statusEl.className = 'auto-sync-status inactive';
                btnStart.style.display = 'inline-block';
                btnStop.style.display = 'none';
            }
        }
        
        // Atualizar countdown
        function updateCountdown() {
            if (autoSyncEnabled && nextAutoSync) {
                const now = new Date();
                const diff = nextAutoSync - now;
                
                if (diff > 0) {
                    const hours = Math.floor(diff / (1000 * 60 * 60));
                    const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                    const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                    
                    const countdownEl = document.getElementById('countdown');
                    if (countdownEl) {
                        countdownEl.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                }
            }
        }
        
        // Iniciar countdown quando carrega a página
        setInterval(updateCountdown, 1000);
        
        // Verificar se deve executar sincronização automática
        async function checkAutoSync() {
            try {
                const form = new FormData();
                form.append('action', 'auto_sync_check');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.should_sync && !syncing) {
                    systemLog('⏰ HORA DE SINCRONIZAR! Executando sincronização completa...', 'warning');
                    systemLog('🚀 Iniciando sincronização automática de TODOS os leads...', 'success');
                    
                    // Ativar aba de sincronização para mostrar progresso
                    showTab('sync');
                    
                    // Executar sincronização completa
                    startSync(true);
                } else if (data.should_sync && syncing) {
                    systemLog('⏰ Sync deveria executar mas já está sincronizando', 'warning');
                }
                // Atualizar última verificação
                document.getElementById('lastCheck').textContent = `🔍 Última verificação: ${new Date().toLocaleTimeString()}`;
            } catch (error) {
                systemLog('❌ Erro ao verificar auto-sync: ' + error.message, 'error');
            }
        }
        
        // Inicializar interface quando carrega
        document.addEventListener('DOMContentLoaded', function() {
            updateAutoSyncStatus();
            // Verificar status do daemon na inicialização
            checkDaemonStatus();
            // Verificar status a cada 30 segundos
            setInterval(checkDaemonStatus, 30000);
            // Verificar se deve sincronizar a cada 60 segundos
            setInterval(checkAutoSync, 60000);
            
            // Auto-iniciar daemon se não estiver rodando
            setTimeout(() => {
                if (!autoSyncEnabled) {
                    startAutoSync();
                }
            }, 3000);
        });
        
        function log(msg, type = '', logId = 'syncLog') {
            const logEl = document.getElementById(logId);
            logEl.style.display = 'block';
            const time = new Date().toLocaleTimeString();
            logEl.innerHTML += `<div class="${type}">[${time}] ${msg}</div>`;
            logEl.scrollTop = logEl.scrollHeight;
            
            // Também adicionar ao log do sistema
            const systemLog = document.getElementById('systemLog');
            if (systemLog && logId !== 'systemLog') {
                systemLog.innerHTML += `<div class="${type}">[${time}] ${msg}</div>`;
                systemLog.scrollTop = systemLog.scrollHeight;
            }
        }
        
        function systemLog(msg, type = '') {
            log(msg, type, 'systemLog');
        }
        
        function clearLogs() {
            document.getElementById('systemLog').innerHTML = '';
            systemLog('🗑️ Logs limpos pelo usuário', 'warning');
        }
        
        function downloadLogs() {
            const logs = document.getElementById('systemLog').innerText;
            const blob = new Blob([logs], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `bmz_sync_logs_${new Date().toISOString().slice(0,19).replace(/:/g,'-')}.txt`;
            a.click();
            window.URL.revokeObjectURL(url);
            systemLog('💾 Logs baixados', 'success');
        }
        
        async function startSync(isAutomatic = false) {
            if (syncing) return;
            syncing = true;
            
            const btn = document.getElementById('syncBtn');
            const progress = document.getElementById('progress');
            const bar = document.getElementById('progressBar');
            const status = document.getElementById('status');
            
            btn.disabled = true;
            btn.textContent = isAutomatic ? '🤖 Sincronização Automática...' : '⏳ Sincronizando...';
            progress.style.display = 'block';
            
            const syncType = isAutomatic ? '🤖 SINCRONIZAÇÃO AUTOMÁTICA' : '🚀 Sincronização manual';
            log(`${syncType} iniciada...`, 'success');
            
            if (isAutomatic) {
                systemLog('🔄 Executando sincronização completa automaticamente...', 'success');
                systemLog('📄 Processando todas as páginas da API do Kommo...', '');
            }
            
            let page = 1, totalSynced = 0, totalLeads = 0;
            
            try {
                while (true) {
                    const pageMsg = `📄 Processando página ${page}...`;
                    log(pageMsg);
                    
                    if (isAutomatic) {
                        systemLog(`📄 API: Buscando página ${page} (250 leads/página)`, '');
                    }
                    
                    const form = new FormData();
                    form.append('action', 'sync');
                    form.append('page', page);
                    form.append('total_synced', totalSynced);
                    
                    const resp = await fetch(location.href, { method: 'POST', body: form });
                    const data = await resp.json();
                    
                    if (data.error) throw new Error(data.error);
                    
                    totalSynced = data.total_synced;
                    totalLeads = data.total_leads;
                    
                    document.getElementById('total').textContent = totalLeads.toLocaleString();
                    document.getElementById('synced').textContent = totalSynced.toLocaleString();
                    
                    const pct = totalLeads > 0 ? Math.round((totalSynced / totalLeads) * 100) : 0;
                    bar.style.width = pct + '%';
                    status.textContent = `${totalSynced}/${totalLeads} (${pct}%)`;
                    
                    const successMsg = `✅ Página ${page}: ${data.batch_synced} leads processados`;
                    log(successMsg, 'success');
                    
                    if (isAutomatic) {
                        systemLog(`💾 Banco: Inseridos ${data.batch_synced} leads da página ${page}`, 'success');
                        systemLog(`📊 Progresso: ${totalSynced}/${totalLeads} leads (${pct}%)`, '');
                    }
                    
                    if (data.finished) {
                        const finishMsg = `🎉 Sincronização concluída! Total: ${totalSynced} leads`;
                        log(finishMsg, 'success');
                        
                        if (isAutomatic) {
                            systemLog(`🎉 SINCRONIZAÇÃO AUTOMÁTICA CONCLUÍDA!`, 'success');
                            systemLog(`📈 Total processado: ${totalSynced} leads`, 'success');
                            systemLog(`⏰ Próxima sincronização automática em 5 minutos`, '');
                            
                            // Atualizar contador de leads na primeira aba
                            document.getElementById('current').textContent = totalSynced.toLocaleString();
                        }
                        break;
                    }
                    
                    page = data.page;
                    await new Promise(r => setTimeout(r, 200));
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error');
                if (isAutomatic) {
                    systemLog(`❌ ERRO na sincronização automática: ${error.message}`, 'error');
                }
            }
            
            syncing = false;
            btn.disabled = false;
            btn.textContent = '🚀 Sincronizar TODOS (250/página)';
            
            // Não recarregar se for automático
            if (!isAutomatic) {
                setTimeout(() => location.reload(), 3000);
            } else {
                // Se foi automático, voltar para aba de logs após 5 segundos
                setTimeout(() => showTab('logs'), 5000);
            }
        }
        
        async function testLead() {
            const leadId = document.getElementById('leadId').value;
            if (!leadId) {
                alert('Digite o ID do lead');
                return;
            }
            
            log('🧪 Testando lead #' + leadId + '...', 'success', 'testResult');
            
            try {
                const form = new FormData();
                form.append('action', 'test_lead');
                form.append('lead_id', leadId);
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Lead inserido com sucesso!', 'success', 'testResult');
                    log(`📊 Linhas afetadas: ${data.rows_affected}`, '', 'testResult');
                    log(`📋 Nome: ${data.lead_data.name}`, '', 'testResult');
                    log(`💰 Valor: R$ ${data.lead_data.price || 0}`, '', 'testResult');
                    
                    // Mostrar campos mapeados
                    log('🔧 Campos personalizados mapeados:', '', 'testResult');
                    Object.entries(data.mapped_fields).forEach(([key, value]) => {
                        if (value) log(`  • ${key}: ${value}`, '', 'testResult');
                    });
                } else {
                    log('❌ Falha na inserção', 'error', 'testResult');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'testResult');
            }
        }
        
        async function checkTable() {
            log('🔍 Verificando estrutura da tabela...', 'success', 'tableResult');
            
            try {
                const form = new FormData();
                form.append('action', 'check_table');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.error) throw new Error(data.error);
                
                if (data.exists) {
                    log('✅ Tabela exists', 'success', 'tableResult');
                    log(`📊 Colunas: ${data.columns}`, '', 'tableResult');
                    log(`📋 Registros: ${data.records}`, '', 'tableResult');
                    
                    log('🔧 Estrutura da tabela:', '', 'tableResult');
                    data.structure.forEach(col => {
                        log(`  • ${col.Field} (${col.Type})`, '', 'tableResult');
                    });
                } else {
                    log('❌ Tabela não existe', 'error', 'tableResult');
                    log(data.message, 'warning', 'tableResult');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'tableResult');
            }
        }
        
        async function fixTable() {
            if (!confirm('⚠️ Isto vai RECRIAR a tabela, removendo todos os dados. Continuar?')) {
                return;
            }
            
            log('🔧 Recriando tabela...', 'warning', 'tableResult');
            
            try {
                const form = new FormData();
                form.append('action', 'fix_table');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Tabela recriada com sucesso!', 'success', 'tableResult');
                    log(data.message, 'success', 'tableResult');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    log('❌ Falha ao recriar tabela', 'error', 'tableResult');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'tableResult');
            }
        }
        
        // Funções Meta Ads
        async function testMetaConnection() {
            log('🔍 Testando conexão com Meta Ads...', 'success', 'metaLog');
            
            try {
                const form = new FormData();
                form.append('action', 'meta_test');
                form.append('test_type', 'connection');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Conexão estabelecida com sucesso!', 'success', 'metaLog');
                    log(`📊 Contas de anúncios disponíveis: ${data.available_accounts.length}`, '', 'metaLog');
                    
                    data.available_accounts.forEach((account, index) => {
                        log(`  ${index + 1}. ${account.name} (${account.id}) - Status: ${account.account_status}`, '', 'metaLog');
                    });
                    
                    log(`📋 Dados completos:`, '', 'metaLog');
                    log(JSON.stringify(data.data, null, 2), '', 'metaLog');
                } else {
                    log('❌ Falha na conexão', 'error', 'metaLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'metaLog');
            }
        }
        
        async function getCampaigns() {
            log('📊 Buscando campanhas do Meta Ads...', 'success', 'metaLog');
            
            try {
                const form = new FormData();
                form.append('action', 'meta_test');
                form.append('test_type', 'campaigns');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const responseText = await resp.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    log(`❌ Resposta não é JSON válido:`, 'error', 'metaLog');
                    log(`📝 Resposta bruta: ${responseText.substring(0, 500)}...`, 'error', 'metaLog');
                    throw new Error('Resposta inválida do servidor');
                }
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Campanhas obtidas com sucesso!', 'success', 'metaLog');
                    log(`🎯 Conta usada: ${data.account_name} (${data.account})`, '', 'metaLog');
                    log(`📊 Total de campanhas: ${data.data.data ? data.data.data.length : 0}`, '', 'metaLog');
                    
                    if (data.data.data && data.data.data.length > 0) {
                        log(`📋 Lista de campanhas:`, '', 'metaLog');
                        data.data.data.forEach((campaign, index) => {
                            log(`  ${index + 1}. ${campaign.name} (${campaign.id}) - Status: ${campaign.status}`, '', 'metaLog');
                        });
                    }
                    
                    log(`📋 Dados completos:`, '', 'metaLog');
                    log(JSON.stringify(data.data, null, 2), '', 'metaLog');
                    
                    log(`📊 Contas disponíveis para referência:`, '', 'metaLog');
                    data.available_accounts.forEach((account, index) => {
                        log(`  ${index + 1}. ${account.name} (${account.id})`, '', 'metaLog');
                    });
                } else {
                    log('❌ Falha ao obter campanhas', 'error', 'metaLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'metaLog');
                // Tentar pegar a resposta bruta do erro
                try {
                    const respText = await resp.text();
                    log(`📝 Resposta HTML/erro: ${respText.substring(0, 300)}...`, 'error', 'metaLog');
                } catch (e) {
                    log('❌ Não foi possível obter resposta bruta', 'error', 'metaLog');
                }
            }
        }
        
        async function getAdSets() {
            log('🎯 Buscando ad sets do Meta Ads...', 'success', 'metaLog');
            
            try {
                const form = new FormData();
                form.append('action', 'meta_test');
                form.append('test_type', 'adsets');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const responseText = await resp.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    log(`❌ Resposta não é JSON válido:`, 'error', 'metaLog');
                    log(`📝 Resposta bruta: ${responseText.substring(0, 500)}...`, 'error', 'metaLog');
                    throw new Error('Resposta inválida do servidor');
                }
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Ad Sets obtidos com sucesso!', 'success', 'metaLog');
                    log(`🎯 Conta usada: ${data.account_name} (${data.account})`, '', 'metaLog');
                    log(`📊 Total de ad sets: ${data.data.data ? data.data.data.length : 0}`, '', 'metaLog');
                    
                    if (data.data.data && data.data.data.length > 0) {
                        log(`📋 Lista de ad sets:`, '', 'metaLog');
                        data.data.data.forEach((adset, index) => {
                            log(`  ${index + 1}. ${adset.name} (${adset.id}) - Status: ${adset.status}`, '', 'metaLog');
                        });
                    }
                    
                    log(`📋 Dados completos:`, '', 'metaLog');
                    log(JSON.stringify(data.data, null, 2), '', 'metaLog');
                    
                    log(`📊 Contas disponíveis para referência:`, '', 'metaLog');
                    data.available_accounts.forEach((account, index) => {
                        log(`  ${index + 1}. ${account.name} (${account.id})`, '', 'metaLog');
                    });
                } else {
                    log('❌ Falha ao obter ad sets', 'error', 'metaLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'metaLog');
            }        
        }
        
        async function getAds() {
            log('📱 Buscando anúncios do Meta Ads...', 'success', 'metaLog');
            
            try {
                const form = new FormData();
                form.append('action', 'meta_test');
                form.append('test_type', 'ads');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const responseText = await resp.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    log(`❌ Resposta não é JSON válido:`, 'error', 'metaLog');
                    log(`📝 Resposta bruta: ${responseText.substring(0, 500)}...`, 'error', 'metaLog');
                    throw new Error('Resposta inválida do servidor');
                }
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Anúncios obtidos com sucesso!', 'success', 'metaLog');
                    log(`🎯 Conta usada: ${data.account_name} (${data.account})`, '', 'metaLog');
                    log(`📊 Total de anúncios: ${data.data.data ? data.data.data.length : 0}`, '', 'metaLog');
                    
                    if (data.data.data && data.data.data.length > 0) {
                        log(`📋 Lista de anúncios:`, '', 'metaLog');
                        data.data.data.slice(0, 10).forEach((ad, index) => {
                            log(`  ${index + 1}. ${ad.name} (${ad.id}) - Status: ${ad.status}`, '', 'metaLog');
                            log(`     ↳ Campaign: ${ad.campaign_id} | AdSet: ${ad.adset_id}`, '', 'metaLog');
                        });
                        if (data.data.data.length > 10) {
                            log(`  ... e mais ${data.data.data.length - 10} anúncios`, '', 'metaLog');
                        }
                    }
                    
                    log(`📋 Dados completos:`, '', 'metaLog');
                    log(JSON.stringify(data.data, null, 2), '', 'metaLog');
                } else {
                    log('❌ Falha ao obter anúncios', 'error', 'metaLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'metaLog');
            }
        }
        
        async function getInsights() {
            log('📈 Buscando métricas do Meta Ads (últimos 30 dias)...', 'success', 'metaLog');
            
            try {
                const form = new FormData();
                form.append('action', 'meta_test');
                form.append('test_type', 'insights');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const responseText = await resp.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    log(`❌ Resposta não é JSON válido:`, 'error', 'metaLog');
                    log(`📝 Resposta bruta: ${responseText.substring(0, 500)}...`, 'error', 'metaLog');
                    throw new Error('Resposta inválida do servidor');
                }
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Métricas obtidas com sucesso!', 'success', 'metaLog');
                    log(`🎯 Conta usada: ${data.account_name} (${data.account})`, '', 'metaLog');
                    log(`📅 Período: ${data.date_range}`, '', 'metaLog');
                    log(`📊 Total de registros: ${data.data.data ? data.data.data.length : 0}`, '', 'metaLog');
                    
                    if (data.data.data && data.data.data.length > 0) {
                        log(`📈 Top 5 anúncios por performance:`, '', 'metaLog');
                        
                        // Ordenar por impressões e mostrar top 5
                        const sortedAds = data.data.data
                            .sort((a, b) => (parseInt(b.impressions) || 0) - (parseInt(a.impressions) || 0))
                            .slice(0, 5);
                            
                        sortedAds.forEach((insight, index) => {
                            const impressions = insight.impressions || '0';
                            const clicks = insight.clicks || '0';
                            const spend = insight.spend || '0';
                            const ctr = insight.ctr || '0';
                            
                            log(`  ${index + 1}. ${insight.ad_name || 'Sem nome'} (ID: ${insight.ad_id})`, '', 'metaLog');
                            log(`     📊 ${impressions} impressões | 👆 ${clicks} clicks | 💰 R$ ${spend} | CTR: ${ctr}%`, '', 'metaLog');
                        });
                        
                        // Calcular totais
                        const totalSpend = data.data.data.reduce((sum, item) => sum + (parseFloat(item.spend) || 0), 0);
                        const totalClicks = data.data.data.reduce((sum, item) => sum + (parseInt(item.clicks) || 0), 0);
                        const totalImpressions = data.data.data.reduce((sum, item) => sum + (parseInt(item.impressions) || 0), 0);
                        
                        log(`💰 TOTAIS: R$ ${totalSpend.toFixed(2)} gastos | ${totalClicks} clicks | ${totalImpressions} impressões`, 'success', 'metaLog');
                    }
                    
                    log(`📋 Dados completos:`, '', 'metaLog');
                    log(JSON.stringify(data.data, null, 2), '', 'metaLog');
                } else {
                    log('❌ Falha ao obter métricas', 'error', 'metaLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'metaLog');
            }
        }
        
        async function syncMetaToDB() {
            const btn = document.getElementById('syncMetaBtn');
            const originalText = btn.textContent;
            
            log('🚀 Iniciando sincronização Meta Ads → MySQL...', 'success', 'metaLog');
            btn.disabled = true;
            btn.textContent = '⏳ Sincronizando...';
            
            try {
                const form = new FormData();
                form.append('action', 'meta_test');
                form.append('test_type', 'sync_meta');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const responseText = await resp.text();
                
                let data;
                try {
                    data = JSON.parse(responseText);
                } catch (parseError) {
                    log(`❌ Resposta não é JSON válido:`, 'error', 'metaLog');
                    log(`📝 Resposta bruta: ${responseText.substring(0, 500)}...`, 'error', 'metaLog');
                    throw new Error('Resposta inválida do servidor');
                }
                
                if (data.error) throw new Error(data.error);
                
                if (data.success) {
                    log('✅ Sincronização COMPLETA concluída com sucesso!', 'success', 'metaLog');
                    log(`📊 Dados sincronizados:`, 'success', 'metaLog');
                    log(`  • ${data.synced.campaigns || 0} Campanhas`, '', 'metaLog');
                    log(`  • ${data.synced.adsets || 0} Ad Sets`, '', 'metaLog');
                    log(`  • ${data.synced.ads || 0} Anúncios`, '', 'metaLog');
                    log(`  • ${data.synced.creatives || 0} Criativos`, '', 'metaLog');
                    log(`🏢 Contas processadas: ${data.processed_accounts}`, '', 'metaLog');
                    log(`📈 Total de itens: ${data.total_items}`, 'success', 'metaLog');
                    
                    if (data.errors && data.errors.length > 0) {
                        log(`⚠️ Avisos/Erros encontrados:`, 'warning', 'metaLog');
                        data.errors.forEach(error => {
                            log(`  • ${error}`, 'warning', 'metaLog');
                        });
                    }
                    
                    log('💾 Dados salvos em 4 tabelas: meta_campaigns, meta_adsets, meta_ads, meta_creatives!', 'success', 'metaLog');
                    log('🔗 Correlação: leads.id_ad ↔ meta_ads.ad_id', '', 'metaLog');
                    log('📊 Agora você tem: links, previews, métricas detalhadas, criativos completos!', 'success', 'metaLog');
                } else {
                    log('❌ Falha na sincronização', 'error', 'metaLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'metaLog');
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
            }
        }
        
        // Funções Api4com
        async function testApi4comConnection() {
            log('🔍 Testando conexão com Api4com...', 'success', 'api4comLog');
            
            try {
                const form = new FormData();
                form.append('action', 'api4com_test');
                form.append('test_type', 'connection');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.success) {
                    log('✅ Conexão Api4com OK!', 'success', 'api4comLog');
                    log(`📞 Chamadas encontradas: ${data.calls_count}`, '', 'api4comLog');
                    if (data.api_response) {
                        log(`📝 Resposta: ${JSON.stringify(data.api_response, null, 2)}`, '', 'api4comLog');
                    }
                } else {
                    log(`❌ Erro: ${data.error}`, 'error', 'api4comLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'api4comLog');
            }
        }
        
        async function getApi4comCalls() {
            log('📞 Buscando chamadas dos últimos 7 dias...', 'success', 'api4comLog');
            
            try {
                const form = new FormData();
                form.append('action', 'api4com_test');
                form.append('test_type', 'get_calls');
                
                const resp = await fetch(location.href, { method: 'POST', body: form });
                const data = await resp.json();
                
                if (data.success) {
                    log(`✅ ${data.message}`, 'success', 'api4comLog');
                    log(`📅 Período: ${data.period}`, '', 'api4comLog');
                    
                    if (data.calls && data.calls.length > 0) {
                        log('📋 Primeiras chamadas encontradas:', '', 'api4comLog');
                        data.calls.slice(0, 3).forEach((call, index) => {
                            log(`  ${index + 1}. ID: ${call.id} | ${call.caller} → ${call.called} | Duração: ${call.duration}s`, '', 'api4comLog');
                        });
                    }
                } else {
                    log(`❌ Erro: ${data.error}`, 'error', 'api4comLog');
                }
                
            } catch (error) {
                log(`❌ Erro: ${error.message}`, 'error', 'api4comLog');
            }
        }
        
        async function syncApi4comToDB() {
            const btn = document.getElementById('syncApi4comBtn');
            const originalText = btn.textContent;
            const progressBar = document.getElementById('api4comProgressBar');
            const progressDiv = document.getElementById('api4comProgress');
            const statusDiv = document.getElementById('api4comStatus');
            
            log('🚀 Iniciando sincronização Api4com → MySQL...', 'success', 'api4comLog');
            btn.disabled = true;
            btn.textContent = '⏳ Sincronizando...';
            progressDiv.style.display = 'block';
            
            const dateFrom = document.getElementById('api4com_date_from').value;
            const dateTo = document.getElementById('api4com_date_to').value;
            
            if (!dateFrom || !dateTo) {
                log('❌ Selecione as datas de início e fim', 'error', 'api4comLog');
                btn.disabled = false;
                btn.textContent = originalText;
                progressDiv.style.display = 'none';
                return;
            }
            
            let totalSynced = 0;
            let page = 1;
            let hasMore = true;
            
            try {
                while (hasMore) {
                    const form = new FormData();
                    form.append('action', 'api4com_test');
                    form.append('test_type', 'sync_calls');
                    form.append('date_from', dateFrom);
                    form.append('date_to', dateTo);
                    form.append('page', page.toString());
                    
                    const resp = await fetch(location.href, { method: 'POST', body: form });
                    const data = await resp.json();
                    
                    if (!data.success) {
                        throw new Error(data.error || 'Erro desconhecido');
                    }
                    
                    totalSynced += data.synced;
                    hasMore = !data.finished && data.has_more;
                    
                    const progress = Math.min(100, (page * 20)); // Estimativa
                    progressBar.style.width = progress + '%';
                    statusDiv.textContent = `Página ${page}: ${data.synced} chamadas sincronizadas`;
                    
                    log(`📄 ${data.message}`, 'success', 'api4comLog');
                    
                    if (hasMore) {
                        page++;
                        await new Promise(resolve => setTimeout(resolve, 500)); // Pausa entre páginas
                    }
                }
                
                progressBar.style.width = '100%';
                statusDiv.textContent = `✅ Concluído! Total: ${totalSynced} chamadas sincronizadas`;
                log(`🎉 Sincronização COMPLETA! Total de ${totalSynced} chamadas sincronizadas`, 'success', 'api4comLog');
                
            } catch (error) {
                log(`❌ Erro na sincronização: ${error.message}`, 'error', 'api4comLog');
                statusDiv.textContent = `❌ Erro: ${error.message}`;
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
                
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                    statusDiv.textContent = '';
                }, 5000);
            }
        }
        
        // Função de Sincronização Completa
        async function startCompleteSync() {
            const btn = document.getElementById('completeSyncBtn');
            const originalText = btn.textContent;
            const progressDiv = document.getElementById('completeProgress');
            const progressBar = document.getElementById('completeProgressBar');
            const statusDiv = document.getElementById('completeStatus');
            const statusGrid = document.getElementById('syncStatusGrid');
            
            btn.disabled = true;
            btn.textContent = '⏳ Sincronizando...';
            progressDiv.style.display = 'block';
            statusGrid.style.display = 'block';
            statusDiv.textContent = 'Iniciando sincronização completa...';
            
            log('🚀 INICIANDO SINCRONIZAÇÃO COMPLETA BMZ', 'success', 'completeLog');
            
            let totalProgress = 0;
            const steps = 3; // Kommo, Meta, Api4com
            
            try {
                // 1. SINCRONIZAR KOMMO
                document.getElementById('kommoStatus').innerHTML = '🔄 Sincronizando...';
                document.getElementById('kommoStatus').style.color = 'orange';
                log('📊 Iniciando sincronização Kommo...', '', 'completeLog');
                
                let kommoTotal = 0;
                let page = 1;
                let hasMore = true;
                
                while (hasMore) {
                    const form = new FormData();
                    form.append('action', 'sync');
                    form.append('page', page.toString());
                    form.append('total_synced', kommoTotal.toString());
                    
                    const resp = await fetch(location.href, { method: 'POST', body: form });
                    const data = await resp.json();
                    
                    if (!data.finished) {
                        kommoTotal += data.batch_synced;
                        document.getElementById('kommoProgress').textContent = `${kommoTotal} leads sincronizados`;
                        page++;
                    } else {
                        hasMore = false;
                    }
                }
                
                document.getElementById('kommoStatus').innerHTML = '✅ Concluído';
                document.getElementById('kommoStatus').style.color = 'green';
                totalProgress += 33;
                progressBar.style.width = totalProgress + '%';
                log(`✅ Kommo: ${kommoTotal} leads sincronizados`, 'success', 'completeLog');
                
                // 2. SINCRONIZAR META ADS
                document.getElementById('metaStatus').innerHTML = '🔄 Sincronizando...';
                document.getElementById('metaStatus').style.color = 'orange';
                log('📱 Iniciando sincronização Meta Ads...', '', 'completeLog');
                
                const metaForm = new FormData();
                metaForm.append('action', 'meta_test');
                metaForm.append('test_type', 'sync_meta');
                
                const metaResp = await fetch(location.href, { method: 'POST', body: metaForm });
                const metaData = await resp.json();
                
                if (metaData.success) {
                    const metaTotal = (metaData.synced?.campaigns || 0) + (metaData.synced?.adsets || 0) + (metaData.synced?.ads || 0);
                    document.getElementById('metaProgress').textContent = `${metaTotal} itens sincronizados`;
                    document.getElementById('metaStatus').innerHTML = '✅ Concluído';
                    document.getElementById('metaStatus').style.color = 'green';
                    log(`✅ Meta Ads: ${metaTotal} itens sincronizados`, 'success', 'completeLog');
                } else {
                    throw new Error('Erro na sincronização Meta Ads');
                }
                
                totalProgress += 33;
                progressBar.style.width = totalProgress + '%';
                
                // 3. SINCRONIZAR API4COM
                document.getElementById('api4comStatus').innerHTML = '🔄 Sincronizando...';
                document.getElementById('api4comStatus').style.color = 'orange';
                log('📞 Iniciando sincronização Api4com...', '', 'completeLog');
                
                let api4comTotal = 0;
                let api4comPage = 1;
                let api4comHasMore = true;
                
                while (api4comHasMore) {
                    const api4comForm = new FormData();
                    api4comForm.append('action', 'api4com_test');
                    api4comForm.append('test_type', 'sync_calls');
                    api4comForm.append('page', api4comPage.toString());
                    
                    const api4comResp = await fetch(location.href, { method: 'POST', body: api4comForm });
                    const api4comData = await api4comResp.json();
                    
                    if (api4comData.success && !api4comData.finished) {
                        api4comTotal += api4comData.synced;
                        document.getElementById('api4comProgress').textContent = `${api4comTotal} chamadas sincronizadas`;
                        api4comPage++;
                    } else {
                        api4comHasMore = false;
                    }
                }
                
                document.getElementById('api4comStatus').innerHTML = '✅ Concluído';
                document.getElementById('api4comStatus').style.color = 'green';
                totalProgress = 100;
                progressBar.style.width = totalProgress + '%';
                log(`✅ Api4com: ${api4comTotal} chamadas sincronizadas`, 'success', 'completeLog');
                
                // FINALIZAÇÃO
                statusDiv.innerHTML = '🎉 <strong>SINCRONIZAÇÃO COMPLETA FINALIZADA!</strong>';
                statusDiv.style.color = 'green';
                log('🎉 SINCRONIZAÇÃO COMPLETA FINALIZADA COM SUCESSO!', 'success', 'completeLog');
                
            } catch (error) {
                log(`❌ Erro na sincronização: ${error.message}`, 'error', 'completeLog');
                statusDiv.innerHTML = `❌ Erro: ${error.message}`;
                statusDiv.style.color = 'red';
            } finally {
                btn.disabled = false;
                btn.textContent = originalText;
                
                setTimeout(() => {
                    progressDiv.style.display = 'none';
                    statusDiv.textContent = '';
                }, 10000);
            }
        }
        
        // Função para atualizar informações de debug
        async function refreshDebugInfo() {
            document.getElementById('metaCampaignsCount').textContent = 'Carregando...';
            document.getElementById('metaDebugStatus').innerHTML = '🔍 Verificando...';
            document.getElementById('api4comCallsCount').textContent = 'Carregando...';
            document.getElementById('api4comDebugStatus').innerHTML = '🔍 Verificando...';
            
            // Verificar Meta Ads
            try {
                const metaForm = new FormData();
                metaForm.append('action', 'meta_test');
                metaForm.append('test_type', 'connection');
                
                const metaResp = await fetch(location.href, { method: 'POST', body: metaForm });
                const metaData = await metaResp.json();
                
                if (metaData.success) {
                    document.getElementById('metaCampaignsCount').textContent = 'Conexão OK';
                    document.getElementById('metaDebugStatus').innerHTML = '<span style="color: green;">✅ Funcionando</span>';
                } else {
                    document.getElementById('metaCampaignsCount').textContent = 'Erro';
                    document.getElementById('metaDebugStatus').innerHTML = '<span style="color: red;">❌ Erro</span>';
                }
            } catch (error) {
                document.getElementById('metaDebugStatus').innerHTML = '<span style="color: red;">❌ Erro de conexão</span>';
            }
            
            // Verificar Api4com
            try {
                const api4comForm = new FormData();
                api4comForm.append('action', 'api4com_test');
                api4comForm.append('test_type', 'connection');
                
                const api4comResp = await fetch(location.href, { method: 'POST', body: api4comForm });
                const api4comData = await api4comResp.json();
                
                if (api4comData.success) {
                    document.getElementById('api4comCallsCount').textContent = api4comData.calls_count || 'OK';
                    document.getElementById('api4comExtensions').textContent = 'Conectado';
                    document.getElementById('api4comDebugStatus').innerHTML = '<span style="color: green;">✅ Funcionando</span>';
                } else {
                    document.getElementById('api4comCallsCount').textContent = 'Erro';
                    document.getElementById('api4comDebugStatus').innerHTML = '<span style="color: red;">❌ Erro</span>';
                }
            } catch (error) {
                document.getElementById('api4comDebugStatus').innerHTML = '<span style="color: red;">❌ Erro de conexão</span>';
            }
        }
        
        // Carregar informações de debug ao abrir a aba
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-refresh debug info quando abrir a aba debug
            const debugTab = document.querySelector('button[onclick="showTab(\'debug\')"]');
            if (debugTab) {
                debugTab.addEventListener('click', function() {
                    setTimeout(refreshDebugInfo, 500);
                });
            }
        });
    </script>
</body>
</html>