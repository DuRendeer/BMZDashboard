<?php
session_start();

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

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if ($username === 'Admin' && $password === 'Formularios2025') {
        $_SESSION['admin_logged'] = true;
        header('Location: admin_dashboard.php');
        exit;
    } else {
        $message = '<div class="fixed top-4 right-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded z-50">
                Credenciais inválidas!
              </div>';
    }
}

if (isset($_SESSION['admin_logged']) && $_SESSION['admin_logged']) {
    header('Location: admin_dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Formulário Anônimo</title>
    <script src="https://cdn.tailwindcss.com"></script>
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
    </style>
</head>
<body class="bg-white min-h-screen flex flex-col items-center justify-center p-8">
    <?php echo $message; ?>
    
    <div class="w-full max-w-md">
        <div class="text-center mb-12">
            <img src="../assets/logo.png" alt="Logo" class="w-32 h-32 rounded-full mx-auto mb-6 border-4 shadow-lg" style="border-color: #1c104f;">
            <h1 class="text-4xl font-bold mb-4" style="color: #1c104f;">Admin</h1>
            <p class="text-gray-600">Acesso restrito - Gerenciamento de formulários</p>
        </div>

        <form method="POST" class="space-y-6">
            <div>
                <label for="username" class="block text-sm font-medium text-gray-700 mb-2">Usuário</label>
                <input 
                    type="text" 
                    id="username"
                    name="username" 
                    required
                    class="w-full p-3 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                    style="border-color: #1c104f;"
                >
            </div>
            
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-2">Senha</label>
                <input 
                    type="password" 
                    id="password"
                    name="password" 
                    required
                    class="w-full p-3 border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-opacity-50 transition-all duration-300"
                    style="border-color: #1c104f;"
                >
            </div>
            
            <div class="flex justify-center">
                <button type="submit" name="login">
                    <span class="button_top">Entrar</span>
                </button>
            </div>
        </form>
        
        <div class="text-center mt-8">
            <a href="../index.php" class="text-gray-600 hover:text-gray-800 underline">
                Voltar ao formulário
            </a>
        </div>
    </div>
</body>
</html>