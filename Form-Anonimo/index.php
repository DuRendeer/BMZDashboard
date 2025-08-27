<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $env = loadEnv(__DIR__ . '/.env');
    
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

$message_status = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $env = loadEnv(__DIR__ . '/.env');
    $token = $env['DISCORD_BOT_TOKEN'] ?? '';
    $userId = '1400126370008404060';
    
    try {
        $pdo = connectDB();
        
        // Pegar todas as perguntas
        $stmt = $pdo->prepare("SELECT * FROM questions WHERE active = 1 ORDER BY position_order, id");
        $stmt->execute();
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $responses = [];
        $hasValidResponses = false;
        
        foreach ($questions as $question) {
            $fieldName = 'question_' . $question['id'];
            $response = $_POST[$fieldName] ?? '';
            
            if ($question['input_type'] === 'checkbox') {
                $response = is_array($_POST[$fieldName] ?? []) ? implode(', ', $_POST[$fieldName]) : '';
            }
            
            if ($question['required'] && empty(trim($response))) {
                header('Location: ' . $_SERVER['PHP_SELF'] . '?error=required');
                exit;
            }
            
            if (!empty(trim($response))) {
                $hasValidResponses = true;
                $responses[] = [
                    'question_id' => $question['id'],
                    'question_text' => $question['question_text'],
                    'response' => trim($response)
                ];
            }
        }
        
        if (!$hasValidResponses) {
            header('Location: ' . $_SERVER['PHP_SELF'] . '?error=empty');
            exit;
        }
        
        // Salvar no banco
        $sessionId = bin2hex(random_bytes(32));
        
        foreach ($responses as $response) {
            $stmt = $pdo->prepare("INSERT INTO responses (session_id, question_id, response_text) VALUES (?, ?, ?)");
            $stmt->execute([$sessionId, $response['question_id'], $response['response']]);
        }
        
        // Enviar para Discord
        if (!empty($token) && !empty($userId)) {
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
                    $message = "**📝 Nova Resposta do Formulário Anônimo**\n\n";
                    $message .= "**ID da Sessão:** " . substr($sessionId, 0, 8) . "...\n";
                    $message .= "**Data:** " . date('d/m/Y H:i:s') . "\n\n";
                    
                    foreach ($responses as $response) {
                        $message .= "**" . $response['question_text'] . "**\n";
                        $message .= $response['response'] . "\n\n";
                    }
                    
                    $messageUrl = "https://discord.com/api/v10/channels/" . $dmChannel['id'] . "/messages";
                    $messageData = json_encode(['content' => $message]);
                    
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
            }
        }
        
        header('Location: ' . $_SERVER['PHP_SELF'] . '?success=1');
        exit;
        
    } catch (Exception $e) {
        header('Location: ' . $_SERVER['PHP_SELF'] . '?error=system');
        exit;
    }
}

// Carregar perguntas do banco
$questions = [];
try {
    $pdo = connectDB();
    $stmt = $pdo->prepare("SELECT * FROM questions WHERE active = 1 ORDER BY position_order, id");
    $stmt->execute();
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Se não conseguir conectar ao banco, usar pergunta padrão
    $questions = [
        [
            'id' => 1,
            'title' => 'Sua Mensagem',
            'question_text' => 'Digite sua mensagem aqui...',
            'input_type' => 'textarea',
            'required' => true,
            'has_image' => false,
            'options' => null
        ]
    ];
}

// Definir mensagem de status baseada nos parâmetros GET
if (isset($_GET['success']) && $_GET['success'] == '1') {
    $message_status = '<div class="fixed top-4 right-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded z-50">
            Formulário enviado com sucesso!
          </div>';
} elseif (isset($_GET['error'])) {
    $errorMsg = "Erro desconhecido.";
    
    switch($_GET['error']) {
        case 'required': $errorMsg = "Por favor, preencha todos os campos obrigatórios."; break;
        case 'empty': $errorMsg = "Por favor, preencha pelo menos um campo."; break;
        case 'system': $errorMsg = "Erro interno do sistema. Tente novamente."; break;
    }
    
    $message_status = '<div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50">
            ' . $errorMsg . '
          </div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formulário Anônimo</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .button {
            --white: #ffe7ff;
            --purple-100: #4c5faf;
            --purple-200: #3d4d8f;
            --purple-300: #2c3a6f;
            --purple-400: #1c104f;
            --purple-500: #1c104f;
            --radius: 18px;

            border-radius: var(--radius);
            outline: none;
            cursor: pointer;
            font-size: 20px;
            font-family: Arial;
            background: transparent;
            letter-spacing: -1px;
            border: 0;
            position: relative;
            width: 220px;
            height: 70px;
        }

        .bg {
            position: absolute;
            inset: 0;
            border-radius: inherit;
            filter: blur(1px);
        }
        .bg::before,
        .bg::after {
            content: "";
            position: absolute;
            inset: 0;
            border-radius: calc(var(--radius) * 1.1);
            background: var(--purple-500);
        }
        .bg::before {
            filter: blur(5px);
            transition: all 0.3s ease;
            box-shadow:
                -7px 6px 0 0 rgb(28 16 79 / 40%),
                -14px 12px 0 0 rgb(28 16 79 / 30%),
                -21px 18px 4px 0 rgb(28 16 79 / 25%),
                -28px 24px 8px 0 rgb(28 16 79 / 15%),
                -35px 30px 12px 0 rgb(28 16 79 / 12%),
                -42px 36px 16px 0 rgb(28 16 79 / 8%),
                -56px 42px 20px 0 rgb(28 16 79 / 5%);
        }

        .wrap {
            border-radius: inherit;
            overflow: hidden;
            height: 100%;
            transform: translate(6px, -6px);
            padding: 3px;
            background: linear-gradient(
                to bottom,
                var(--purple-100) 0%,
                var(--purple-400) 100%
            );
            position: relative;
            transition: all 0.3s ease;
        }

        .outline {
            position: absolute;
            overflow: hidden;
            inset: 0;
            opacity: 0;
            outline: none;
            border-radius: inherit;
            transition: all 0.4s ease;
        }
        .outline::before {
            content: "";
            position: absolute;
            inset: 2px;
            width: 120px;
            height: 300px;
            margin: auto;
            background: linear-gradient(
                to right,
                transparent 0%,
                white 50%,
                transparent 100%
            );
            animation: spin 3s linear infinite;
            animation-play-state: paused;
        }

        .content {
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1;
            position: relative;
            height: 100%;
            gap: 16px;
            border-radius: calc(var(--radius) * 0.85);
            font-weight: 600;
            transition: all 0.3s ease;
            background: linear-gradient(
                to bottom,
                var(--purple-300) 0%,
                var(--purple-400) 100%
            );
            box-shadow:
                inset -2px 12px 11px -5px var(--purple-200),
                inset 1px -3px 11px 0px rgb(0 0 0 / 35%);
        }
        .content::before {
            content: "";
            inset: 0;
            position: absolute;
            z-index: 10;
            width: 80%;
            top: 45%;
            bottom: 35%;
            opacity: 0.7;
            margin: auto;
            background: linear-gradient(to bottom, transparent, var(--purple-400));
            filter: brightness(1.3) blur(5px);
        }

        .char {
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .char span {
            display: block;
            color: transparent;
            position: relative;
        }
        .char span:nth-child(2) {
            margin-left: 5px;
        }
        .char.state-1 span:nth-child(2) {
            margin-right: -3px;
        }
        .char.state-1 span {
            animation: charAppear 1.2s ease backwards calc(var(--i) * 0.03s);
        }
        .char.state-1 span::before,
        .char span::after {
            content: attr(data-label);
            position: absolute;
            color: var(--white);
            text-shadow: -1px 1px 2px var(--purple-500);
            left: 0;
        }
        .char span::before {
            opacity: 0;
            transform: translateY(-100%);
        }
        .char.state-2 {
            position: absolute;
            left: 60px;
        }
        .char.state-2 span::after {
            opacity: 1;
        }

        .icon {
            animation: resetArrow 0.8s cubic-bezier(0.7, -0.5, 0.3, 1.2) forwards;
            z-index: 10;
        }
        .icon div,
        .icon div::before,
        .icon div::after {
            height: 3px;
            border-radius: 1px;
            background-color: var(--white);
        }
        .icon div::before,
        .icon div::after {
            content: "";
            position: absolute;
            right: 0;
            transform-origin: center right;
            width: 14px;
            border-radius: 15px;
            transition: all 0.3s ease;
        }
        .icon div {
            position: relative;
            width: 24px;
            box-shadow: -2px 2px 5px var(--purple-400);
            transform: scale(0.9);
            background: linear-gradient(to bottom, var(--white), var(--purple-100));
            animation: swingArrow 1s ease-in-out infinite;
            animation-play-state: paused;
        }
        .icon div::before {
            transform: rotate(44deg);
            top: 1px;
            box-shadow: 1px -2px 3px -1px var(--purple-400);
            animation: rotateArrowLine 1s linear infinite;
            animation-play-state: paused;
        }
        .icon div::after {
            bottom: 1px;
            transform: rotate(316deg);
            box-shadow: -2px 2px 3px 0 var(--purple-400);
            background: linear-gradient(200deg, var(--white), var(--purple-100));
            animation: rotateArrowLine2 1s linear infinite;
            animation-play-state: paused;
        }

        .path {
            position: absolute;
            z-index: 12;
            bottom: 0;
            left: 0;
            right: 0;
            stroke-dasharray: 150 480;
            stroke-dashoffset: 150;
            pointer-events: none;
        }

        .splash {
            position: absolute;
            top: 0;
            left: 0;
            pointer-events: none;
            stroke-dasharray: 60 60;
            stroke-dashoffset: 60;
            transform: translate(-17%, -31%);
            stroke: var(--purple-300);
        }

        .button:hover .char.state-1 span::before {
            animation: charAppear 0.7s ease calc(var(--i) * 0.03s);
        }

        .button:hover .char.state-1 span::after {
            opacity: 1;
            animation: charDisappear 0.7s ease calc(var(--i) * 0.03s);
        }

        .button:hover .wrap {
            transform: translate(8px, -8px);
        }

        .button:hover .outline {
            opacity: 1;
        }

        .button:hover .outline::before,
        .button:hover .icon div::before,
        .button:hover .icon div::after,
        .button:hover .icon div {
            animation-play-state: running;
        }

        .button:active .bg::before {
            filter: blur(5px);
            opacity: 0.7;
            box-shadow:
                -7px 6px 0 0 rgb(28 16 79 / 40%),
                -14px 12px 0 0 rgb(28 16 79 / 25%),
                -21px 18px 4px 0 rgb(28 16 79 / 15%);
        }
        .button:active .content {
            box-shadow:
                inset -1px 12px 8px -5px rgba(28, 16, 79, 0.4),
                inset 0px -3px 8px 0px var(--purple-200);
        }

        .button:active .outline {
            opacity: 0;
        }

        .button:active .wrap {
            transform: translate(3px, -3px);
        }

        .button:active .splash {
            animation: splash 0.8s cubic-bezier(0.3, 0, 0, 1) forwards 0.05s;
        }

        .button:focus .path {
            animation: path 1.6s ease forwards 0.2s;
        }

        .button:focus .icon {
            animation: arrow 1s cubic-bezier(0.7, -0.5, 0.3, 1.5) forwards;
        }

        .char.state-2 span::after,
        .button:focus .char.state-1 span {
            animation: charDisappear 0.5s ease forwards calc(var(--i) * 0.03s);
        }

        .button:focus .char.state-2 span::after {
            animation: charAppear 1s ease backwards calc(var(--i) * 0.03s);
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @keyframes charAppear {
            0% {
                transform: translateY(50%);
                opacity: 0;
                filter: blur(20px);
            }
            20% {
                transform: translateY(70%);
                opacity: 1;
            }
            50% {
                transform: translateY(-15%);
                opacity: 1;
                filter: blur(0);
            }
            100% {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes charDisappear {
            0% {
                transform: translateY(0);
                opacity: 1;
            }
            100% {
                transform: translateY(-70%);
                opacity: 0;
                filter: blur(3px);
            }
        }

        @keyframes arrow {
            0% { opacity: 1; }
            50% {
                transform: translateX(60px);
                opacity: 0;
            }
            51% {
                transform: translateX(-200px);
                opacity: 0;
            }
            100% {
                transform: translateX(-128px);
                opacity: 1;
            }
        }

        @keyframes swingArrow {
            50% { transform: translateX(5px) scale(0.9); }
        }

        @keyframes rotateArrowLine {
            50% { transform: rotate(30deg); }
            80% { transform: rotate(55deg); }
        }

        @keyframes rotateArrowLine2 {
            50% { transform: rotate(330deg); }
            80% { transform: rotate(300deg); }
        }

        @keyframes resetArrow {
            0% { transform: translateX(-128px); }
            100% { transform: translateX(0); }
        }

        @keyframes path {
            from { stroke: white; }
            to {
                stroke-dashoffset: -480;
                stroke: #4c5faf;
            }
        }

        @keyframes splash {
            to {
                stroke-dasharray: 2 60;
                stroke-dashoffset: -60;
            }
        }
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col items-center justify-center p-8">
    <?php echo $message_status; ?>
    
    <div class="w-full max-w-2xl">
        <div class="text-center mb-12">
            <img src="assets/logo.png" alt="Logo" class="w-32 h-32 rounded-full mx-auto mb-6 border-4 shadow-lg" style="border-color: #1c104f;">
            <h1 class="text-4xl font-bold mb-4" style="color: #1c104f;">Formulário Anônimo</h1>
            <p class="text-gray-600">Todas as respostas são anônimas</p>
        </div>

        <form method="POST" class="space-y-8">
            <?php foreach ($questions as $question): ?>
                <div class="space-y-4">
                    <?php if ($question['has_image'] && !empty($question['image_path'])): ?>
                        <div class="text-center">
                            <img src="<?php echo htmlspecialchars($question['image_path']); ?>" alt="<?php echo htmlspecialchars($question['title']); ?>" class="max-w-xs mx-auto rounded-lg">
                        </div>
                    <?php endif; ?>
                    
                    <div>
                        <label class="block text-lg font-medium mb-3" style="color: #1c104f;">
                            <?php echo htmlspecialchars($question['title']); ?>
                            <?php if ($question['required']): ?>
                                <span class="text-red-500">*</span>
                            <?php endif; ?>
                        </label>
                        
                        <?php
                        $fieldName = 'question_' . $question['id'];
                        $fieldClasses = "w-full p-4 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300";
                        $fieldStyle = "border-color: #1c104f;";
                        $required = $question['required'] ? 'required' : '';
                        
                        switch ($question['input_type']):
                            case 'textarea': ?>
                                <textarea 
                                    name="<?php echo $fieldName; ?>" 
                                    <?php echo $required; ?>
                                    class="<?php echo $fieldClasses; ?> resize-none h-32"
                                    style="<?php echo $fieldStyle; ?>"
                                ></textarea>
                            <?php break;
                            
                            case 'text': ?>
                                <input 
                                    type="text" 
                                    name="<?php echo $fieldName; ?>" 
                                    <?php echo $required; ?>
                                    class="<?php echo $fieldClasses; ?>"
                                    style="<?php echo $fieldStyle; ?>"
                                >
                            <?php break;
                            
                            case 'select':
                                if ($question['options']): ?>
                                    <select 
                                        name="<?php echo $fieldName; ?>" 
                                        <?php echo $required; ?>
                                        class="<?php echo $fieldClasses; ?>"
                                        style="<?php echo $fieldStyle; ?>"
                                    >
                                        <option value="">Selecione uma opção...</option>
                                        <?php foreach (json_decode($question['options']) as $option): ?>
                                            <option value="<?php echo htmlspecialchars($option); ?>"><?php echo htmlspecialchars($option); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                            <?php endif;
                            break;
                            
                            case 'radio':
                                if ($question['options']): ?>
                                    <div class="space-y-3">
                                        <?php foreach (json_decode($question['options']) as $index => $option): ?>
                                            <label class="flex items-center space-x-3 cursor-pointer">
                                                <input 
                                                    type="radio" 
                                                    name="<?php echo $fieldName; ?>" 
                                                    value="<?php echo htmlspecialchars($option); ?>"
                                                    <?php echo $required && $index === 0 ? 'required' : ''; ?>
                                                    class="w-4 h-4"
                                                    style="accent-color: #1c104f;"
                                                >
                                                <span class="text-gray-700"><?php echo htmlspecialchars($option); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                            <?php endif;
                            break;
                            
                            case 'checkbox':
                                if ($question['options']): ?>
                                    <div class="space-y-3">
                                        <?php foreach (json_decode($question['options']) as $option): ?>
                                            <label class="flex items-center space-x-3 cursor-pointer">
                                                <input 
                                                    type="checkbox" 
                                                    name="<?php echo $fieldName; ?>[]" 
                                                    value="<?php echo htmlspecialchars($option); ?>"
                                                    class="w-4 h-4"
                                                    style="accent-color: #1c104f;"
                                                >
                                                <span class="text-gray-700"><?php echo htmlspecialchars($option); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                            <?php endif;
                            break;
                        endswitch; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="flex justify-center">
                <button type="submit" class="button" style="transform: rotate(353deg) skewX(4deg) translateX(-20px);">
                    <div class="bg"></div>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 342 208" height="208" width="342" class="splash">
                        <path stroke-linecap="round" stroke-width="3" d="M54.1054 99.7837C54.1054 99.7837 40.0984 90.7874 26.6893 97.6362C13.2802 104.485 1.5 97.6362 1.5 97.6362"></path>
                        <path stroke-linecap="round" stroke-width="3" d="M285.273 99.7841C285.273 99.7841 299.28 90.7879 312.689 97.6367C326.098 104.486 340.105 95.4893 340.105 95.4893"></path>
                        <path stroke-linecap="round" stroke-width="3" stroke-opacity="0.3" d="M281.133 64.9917C281.133 64.9917 287.96 49.8089 302.934 48.2295C317.908 46.6501 319.712 36.5272 319.712 36.5272"></path>
                        <path stroke-linecap="round" stroke-width="3" stroke-opacity="0.3" d="M281.133 138.984C281.133 138.984 287.96 154.167 302.934 155.746C317.908 157.326 319.712 167.449 319.712 167.449"></path>
                        <path stroke-linecap="round" stroke-width="3" d="M230.578 57.4476C230.578 57.4476 225.785 41.5051 236.061 30.4998C246.337 19.4945 244.686 12.9998 244.686 12.9998"></path>
                        <path stroke-linecap="round" stroke-width="3" d="M230.578 150.528C230.578 150.528 225.785 166.471 236.061 177.476C246.337 188.481 244.686 194.976 244.686 194.976"></path>
                        <path stroke-linecap="round" stroke-width="3" stroke-opacity="0.3" d="M170.392 57.0278C170.392 57.0278 173.89 42.1322 169.571 29.54C165.252 16.9478 168.751 2.05227 168.751 2.05227"></path>
                        <path stroke-linecap="round" stroke-width="3" stroke-opacity="0.3" d="M170.392 150.948C170.392 150.948 173.89 165.844 169.571 178.436C165.252 191.028 168.751 205.924 168.751 205.924"></path>
                        <path stroke-linecap="round" stroke-width="3" d="M112.609 57.4476C112.609 57.4476 117.401 41.5051 107.125 30.4998C96.8492 19.4945 98.5 12.9998 98.5 12.9998"></path>
                        <path stroke-linecap="round" stroke-width="3" d="M112.609 150.528C112.609 150.528 117.401 166.471 107.125 177.476C96.8492 188.481 98.5 194.976 98.5 194.976"></path>
                        <path stroke-linecap="round" stroke-width="3" stroke-opacity="0.3" d="M62.2941 64.9917C62.2941 64.9917 55.4671 49.8089 40.4932 48.2295C25.5194 46.6501 23.7159 36.5272 23.7159 36.5272"></path>
                        <path stroke-linecap="round" stroke-width="3" stroke-opacity="0.3" d="M62.2941 145.984C62.2941 145.984 55.4671 161.167 40.4932 162.746C25.5194 164.326 23.7159 174.449 23.7159 174.449"></path>
                    </svg>

                    <div class="wrap">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 221 42" height="42" width="221" class="path">
                            <path stroke-linecap="round" stroke-width="3" d="M182.674 2H203C211.837 2 219 9.16344 219 18V24C219 32.8366 211.837 40 203 40H18C9.16345 40 2 32.8366 2 24V18C2 9.16344 9.16344 2 18 2H47.8855"></path>
                        </svg>

                        <div class="outline"></div>
                        <div class="content">
                            <span class="char state-1">
                                <span data-label="E" style="--i: 1">E</span>
                                <span data-label="n" style="--i: 2">n</span>
                                <span data-label="v" style="--i: 3">v</span>
                                <span data-label="i" style="--i: 4">i</span>
                                <span data-label="a" style="--i: 5">a</span>
                                <span data-label="r" style="--i: 6">r</span>
                            </span>

                            <div class="icon">
                                <div></div>
                            </div>

                            <span class="char state-2">
                                <span data-label="E" style="--i: 1">E</span>
                                <span data-label="n" style="--i: 2">n</span>
                                <span data-label="v" style="--i: 3">v</span>
                                <span data-label="i" style="--i: 4">i</span>
                                <span data-label="a" style="--i: 5">a</span>
                                <span data-label="d" style="--i: 6">d</span>
                                <span data-label="o" style="--i: 7">o</span>
                            </span>
                        </div>
                    </div>
                </button>
            </div>
        </form>
        

    </div>
</body>
</html>