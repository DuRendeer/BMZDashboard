<?php
session_start();

if (!isset($_SESSION['admin_logged']) || !$_SESSION['admin_logged']) {
    header('Location: admin.php');
    exit;
}

function loadEnv($file) {
    if (!file_exists($file)) return [];
    
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && substr($line, 0, 1) !== '#') {
            list($key, $value) = explode('=', $line, 2);
            $env[trim($key)] = trim($value);
        }
    }
    
    return $env;
}

function connectDB() {
    $env = loadEnv(__DIR__ . '/../.env');
    
    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_NAME'] ?? 'formulario_anonimo';
    $username = $env['DB_USER'] ?? 'root';
    $password = $env['DB_PASSWORD'] ?? '';
    
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch(PDOException $e) {
        die("Erro de conexão: " . $e->getMessage());
    }
}

function sendDiscordReport($responses) {
    $env = loadEnv(__DIR__ . '/../.env');
    $token = $env['DISCORD_BOT_TOKEN'] ?? '';
    $userId = '1400126370008404060';
    
    if (empty($token)) return false;
    
    $dmUrl = "https://discord.com/api/v10/users/@me/channels";
    $dmData = json_encode(['recipient_id' => $userId]);
    
    $dmContext = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => [
                'Authorization: Bot ' . $token,
                'Content-Type: application/json',
                'User-Agent: DiscordBot (FormularioAnonimo, 1.0)'
            ],
            'content' => $dmData,
            'ignore_errors' => true
        ]
    ]);
    
    $dmResponse = @file_get_contents($dmUrl, false, $dmContext);
    
    if ($dmResponse !== false) {
        $dmChannel = json_decode($dmResponse, true);
        
        if (isset($dmChannel['id'])) {
            $report = "**📊 Relatório Completo de Respostas**\n\n";
            $report .= "Total de respostas: " . count($responses) . "\n\n";
            
            $groupedResponses = [];
            foreach ($responses as $response) {
                $sessionId = $response['session_id'];
                if (!isset($groupedResponses[$sessionId])) {
                    $groupedResponses[$sessionId] = [];
                }
                $groupedResponses[$sessionId][] = $response;
            }
            
            $count = 1;
            foreach ($groupedResponses as $sessionId => $sessionResponses) {
                $report .= "**Resposta #{$count}** (ID: " . substr($sessionId, 0, 8) . "...)\n";
                $report .= "Data: " . date('d/m/Y H:i', strtotime($sessionResponses[0]['created_at'])) . "\n";
                
                foreach ($sessionResponses as $response) {
                    $report .= "• **" . $response['question_text'] . "**\n";
                    $report .= "  " . $response['response_text'] . "\n\n";
                }
                
                $report .= "---\n\n";
                $count++;
                
                if (strlen($report) > 1800) {
                    $messageUrl = "https://discord.com/api/v10/channels/" . $dmChannel['id'] . "/messages";
                    $messageData = json_encode(['content' => $report]);
                    
                    $messageContext = stream_context_create([
                        'http' => [
                            'method' => 'POST',
                            'header' => [
                                'Authorization: Bot ' . $token,
                                'Content-Type: application/json',
                                'User-Agent: DiscordBot (FormularioAnonimo, 1.0)'
                            ],
                            'content' => $messageData,
                            'ignore_errors' => true
                        ]
                    ]);
                    
                    @file_get_contents($messageUrl, false, $messageContext);
                    $report = "";
                }
            }
            
            if (!empty($report)) {
                $messageUrl = "https://discord.com/api/v10/channels/" . $dmChannel['id'] . "/messages";
                $messageData = json_encode(['content' => $report]);
                
                $messageContext = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => [
                            'Authorization: Bot ' . $token,
                            'Content-Type: application/json',
                            'User-Agent: DiscordBot (FormularioAnonimo, 1.0)'
                        ],
                        'content' => $messageData,
                        'ignore_errors' => true
                    ]
                ]);
                
                @file_get_contents($messageUrl, false, $messageContext);
            }
            
            return true;
        }
    }
    
    return false;
}

$pdo = connectDB();
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_question':
                $title = $_POST['title'] ?? '';
                $input_type = $_POST['input_type'] ?? 'text';
                $required = isset($_POST['required']) ? 1 : 0;
                $has_image = isset($_POST['has_image']) ? 1 : 0;
                $image_path = $_POST['image_path'] ?? null;
                $options = null;
                
                if (in_array($input_type, ['select', 'radio', 'checkbox']) && !empty($_POST['options'])) {
                    $options = json_encode(array_filter(array_map('trim', explode("\n", $_POST['options']))));
                }
                
                $stmt = $pdo->prepare("INSERT INTO questions (title, question_text, input_type, required, has_image, image_path, options, position_order) VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT IFNULL(MAX(position_order), 0) + 1 FROM questions q))");
                $stmt->execute([$title, $title, $input_type, $required, $has_image, $image_path, $options]);
                
                $message = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">Pergunta adicionada com sucesso!</div>';
                break;
                
            case 'delete_response':
                $id = $_POST['response_id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM responses WHERE id = ?");
                $stmt->execute([$id]);
                $message = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">Resposta removida com sucesso!</div>';
                break;
                
            case 'reorder_questions':
                if (isset($_POST['question_ids']) && is_array($_POST['question_ids'])) {
                    foreach ($_POST['question_ids'] as $order => $questionId) {
                        $stmt = $pdo->prepare("UPDATE questions SET position_order = ? WHERE id = ?");
                        $stmt->execute([$order + 1, $questionId]);
                    }
                    $message = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">Ordem das perguntas atualizada!</div>';
                } else {
                    $message = '<div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50">Erro: dados de reordenação não encontrados!</div>';
                }
                break;
                
            case 'delete_question':
                $id = $_POST['question_id'] ?? 0;
                $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
                $stmt->execute([$id]);
                $message = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">Pergunta removida com sucesso!</div>';
                break;
                
            case 'clear_responses':
                $stmt = $pdo->prepare("DELETE FROM responses");
                $stmt->execute();
                $message = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">Todas as respostas foram removidas!</div>';
                break;
                
            case 'export_responses':
                $stmt = $pdo->prepare("
                    SELECT r.session_id, r.response_text, r.created_at, q.question_text 
                    FROM responses r 
                    JOIN questions q ON r.question_id = q.id 
                    ORDER BY r.created_at DESC, r.session_id
                ");
                $stmt->execute();
                $responses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (sendDiscordReport($responses)) {
                    $message = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">Relatório enviado para Discord!</div>';
                } else {
                    $message = '<div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50">Erro ao enviar relatório!</div>';
                }
                break;
        }
    }
}

$stmt = $pdo->prepare("SELECT * FROM questions ORDER BY position_order, id");
$stmt->execute();
$questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM responses");
$stmt->execute();
$totalResponses = $stmt->fetchColumn();

// Buscar todas as respostas para exibir na tabela
$stmt = $pdo->prepare("
    SELECT r.id, r.session_id, r.response_text, r.created_at, q.title as question_title 
    FROM responses r 
    JOIN questions q ON r.question_id = q.id 
    ORDER BY r.created_at DESC
");
$stmt->execute();
$allResponses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Formulário Anônimo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
    <style>
        button {
            --button_radius: 0.75em;
            --button_color: #e8e8e8;
            --button_outline_color: #000000;
            font-size: 17px;
            font-weight: bold;
            border: none;
            cursor: pointer;
            border-radius: var(--button_radius);
            background: var(--button_outline_color);
        }

        .button_top {
            display: block;
            box-sizing: border-box;
            border: 2px solid var(--button_outline_color);
            border-radius: var(--button_radius);
            padding: 0.75em 1.5em;
            background: var(--button_color);
            color: var(--button_outline_color);
            transform: translateY(-0.2em);
            transition: transform 0.1s ease;
        }

        button:hover .button_top {
            transform: translateY(-0.33em);
        }

        button:active .button_top {
            transform: translateY(0);
        }

        .btn-small {
            font-size: 14px;
        }

        .btn-small .button_top {
            padding: 0.5em 1em;
        }

        .btn-red {
            --button_color: #fee2e2;
            --button_outline_color: #dc2626;
        }

        .btn-green {
            --button_color: #d1fae5;
            --button_outline_color: #16a34a;
        }

        .btn-blue {
            --button_color: #dbeafe;
            --button_outline_color: #2563eb;
        }
        
        .sortable-ghost {
            opacity: 0.4;
            background: #f3f4f6;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <?php echo $message; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-4" style="color: #1c104f;">Dashboard Admin</h1>
            <p class="text-gray-600">Gerenciar perguntas do formulário anônimo</p>
            <p class="text-sm text-gray-500 mt-2">Total de respostas: <?php echo $totalResponses; ?></p>
        </div>

        <div class="flex justify-between mb-8">
            <div class="space-x-4">
                <button onclick="showAddQuestionForm()" class="btn-green">
                    <span class="button_top">Nova Pergunta</span>
                </button>
                <button onclick="showResponsesTable()" class="btn-blue">
                    <span class="button_top">Ver Respostas</span>
                </button>
                <button onclick="exportResponses()" class="btn-blue">
                    <span class="button_top">Exportar Relatório</span>
                </button>
                <button onclick="clearResponses()" class="btn-red">
                    <span class="button_top">Limpar Respostas</span>
                </button>
            </div>
            <div>
                <a href="../index.php" target="_blank">
                    <button>
                        <span class="button_top">Ver Formulário</span>
                    </button>
                </a>
                <a href="?logout=1" onclick="return confirm('Deseja sair?')">
                    <button class="btn-red">
                        <span class="button_top">Sair</span>
                    </button>
                </a>
            </div>
        </div>

        <div id="addQuestionForm" class="hidden mb-8 bg-white p-6 rounded-lg shadow">
            <h3 class="text-xl font-bold mb-4">Adicionar Nova Pergunta</h3>
            <form method="POST">
                <input type="hidden" name="action" value="add_question">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título da Pergunta</label>
                        <input type="text" name="title" required class="w-full p-3 border rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de Input</label>
                        <select name="input_type" class="w-full p-3 border rounded-lg" onchange="toggleOptions(this)">
                            <option value="text">Texto</option>
                            <option value="textarea">Área de Texto</option>
                            <option value="select">Lista Suspensa</option>
                            <option value="radio">Múltipla Escolha</option>
                            <option value="checkbox">Caixas de Seleção</option>
                        </select>
                    </div>
                </div>
                
                <div id="optionsField" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Opções (uma por linha)</label>
                    <textarea name="options" class="w-full p-3 border rounded-lg" rows="4"></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="required" checked class="mr-2">
                            <span class="text-sm font-medium text-gray-700">Campo Obrigatório</span>
                        </label>
                    </div>
                    <div>
                        <label class="flex items-center">
                            <input type="checkbox" name="has_image" class="mr-2" onchange="toggleImageField(this)">
                            <span class="text-sm font-medium text-gray-700">Tem Imagem</span>
                        </label>
                    </div>
                </div>
                
                <div id="imageField" class="mb-4 hidden">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Caminho da Imagem</label>
                    <input type="text" name="image_path" class="w-full p-3 border rounded-lg">
                </div>
                
                <div class="flex space-x-4">
                    <button type="submit" class="btn-green">
                        <span class="button_top">Adicionar</span>
                    </button>
                    <button type="button" onclick="hideAddQuestionForm()">
                        <span class="button_top">Cancelar</span>
                    </button>
                </div>
            </form>
        </div>

        <div id="responsesTable" class="hidden mb-8 bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-xl font-bold">Todas as Respostas</h3>
            </div>
            <div class="p-6">
                <?php if (empty($allResponses)): ?>
                    <p class="text-gray-500 text-center">Nenhuma resposta encontrada.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full border-collapse border border-gray-300">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="border border-gray-300 p-3 text-left">Data</th>
                                    <th class="border border-gray-300 p-3 text-left">Pergunta</th>
                                    <th class="border border-gray-300 p-3 text-left">Resposta</th>
                                    <th class="border border-gray-300 p-3 text-left">Sessão</th>
                                    <th class="border border-gray-300 p-3 text-left">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allResponses as $response): ?>
                                    <tr>
                                        <td class="border border-gray-300 p-3"><?php echo date('d/m/Y H:i', strtotime($response['created_at'])); ?></td>
                                        <td class="border border-gray-300 p-3"><?php echo htmlspecialchars($response['question_title']); ?></td>
                                        <td class="border border-gray-300 p-3"><?php echo htmlspecialchars(substr($response['response_text'], 0, 100)) . (strlen($response['response_text']) > 100 ? '...' : ''); ?></td>
                                        <td class="border border-gray-300 p-3"><?php echo substr($response['session_id'], 0, 8) . '...'; ?></td>
                                        <td class="border border-gray-300 p-3">
                                            <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja remover esta resposta?')">
                                                <input type="hidden" name="action" value="delete_response">
                                                <input type="hidden" name="response_id" value="<?php echo $response['id']; ?>">
                                                <button type="submit" class="btn-red btn-small">
                                                    <span class="button_top">Remover</span>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow">
            <div class="p-6 border-b">
                <h3 class="text-xl font-bold">Perguntas do Formulário</h3>
            </div>
            <div class="p-6">
                <?php if (empty($questions)): ?>
                    <p class="text-gray-500 text-center">Nenhuma pergunta cadastrada.</p>
                <?php else: ?>
                    <div class="mb-4">
                        <button onclick="saveQuestionOrder()" class="btn-green btn-small">
                            <span class="button_top">Salvar Ordem</span>
                        </button>
                        <span class="text-sm text-gray-500 ml-4">Arraste as perguntas para reordenar</span>
                    </div>
                    <div id="questionsList" class="space-y-4">
                        <?php foreach ($questions as $question): ?>
                            <div class="border border-gray-200 rounded-lg p-4 cursor-move" data-id="<?php echo $question['id']; ?>">
                                <div class="flex justify-between items-start">
                                    <div class="flex items-center space-x-4">
                                        <div class="text-gray-400">
                                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M7 2a1 1 0 000 2h6a1 1 0 100-2H7zM4 6a1 1 0 011-1h10a1 1 0 110 2H5a1 1 0 01-1-1zM5 10a1 1 0 000 2h10a1 1 0 100-2H5zM5 14a1 1 0 000 2h10a1 1 0 100-2H5z"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1">
                                            <h4 class="font-bold text-lg">
                                                <?php echo htmlspecialchars($question['title']); ?>
                                            </h4>
                                            <div class="flex space-x-4 text-sm text-gray-500">
                                                <span>Tipo: <?php echo ucfirst($question['input_type']); ?></span>
                                                <span><?php echo $question['required'] ? 'Obrigatório' : 'Opcional'; ?></span>
                                                <?php if ($question['has_image']): ?>
                                                    <span>Com Imagem</span>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($question['options']): ?>
                                                <div class="mt-2">
                                                    <strong>Opções:</strong>
                                                    <span class="text-sm text-gray-600">
                                                        <?php echo implode(', ', json_decode($question['options'])); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ml-4">
                                        <form method="POST" class="inline" onsubmit="return confirm('Tem certeza que deseja remover esta pergunta?')">
                                            <input type="hidden" name="action" value="delete_question">
                                            <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                            <button type="submit" class="btn-red btn-small">
                                                <span class="button_top">Remover</span>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <script>
        function showAddQuestionForm() {
            document.getElementById('addQuestionForm').classList.remove('hidden');
        }
        
        function hideAddQuestionForm() {
            document.getElementById('addQuestionForm').classList.add('hidden');
        }
        
        function toggleOptions(select) {
            const optionsField = document.getElementById('optionsField');
            if (['select', 'radio', 'checkbox'].includes(select.value)) {
                optionsField.classList.remove('hidden');
            } else {
                optionsField.classList.add('hidden');
            }
        }
        
        function toggleImageField(checkbox) {
            const imageField = document.getElementById('imageField');
            if (checkbox.checked) {
                imageField.classList.remove('hidden');
            } else {
                imageField.classList.add('hidden');
            }
        }
        
        function exportResponses() {
            if (confirm('Deseja exportar todas as respostas para o Discord?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="export_responses">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function clearResponses() {
            if (confirm('ATENÇÃO: Esta ação irá remover TODAS as respostas permanentemente. Deseja continuar?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="action" value="clear_responses">';
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function showResponsesTable() {
            const table = document.getElementById('responsesTable');
            if (table.classList.contains('hidden')) {
                table.classList.remove('hidden');
            } else {
                table.classList.add('hidden');
            }
        }
        
        function saveQuestionOrder() {
            const questionItems = document.querySelectorAll('#questionsList > div[data-id]');
            const questionIds = Array.from(questionItems).map(item => item.dataset.id);
            
            console.log('IDs das perguntas na nova ordem:', questionIds);
            
            if (questionIds.length === 0) {
                alert('Nenhuma pergunta encontrada para reordenar');
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="reorder_questions">';
            
            questionIds.forEach((id, index) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'question_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            console.log('Enviando formulário:', form);
            document.body.appendChild(form);
            form.submit();
        }
        
        // Inicializar Sortable
        document.addEventListener('DOMContentLoaded', function() {
            const questionsList = document.getElementById('questionsList');
            if (questionsList) {
                new Sortable(questionsList, {
                    handle: '.cursor-move',
                    animation: 150,
                    ghostClass: 'sortable-ghost'
                });
            }
        });
    </script>
</body>
</html>

<?php
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}
?>