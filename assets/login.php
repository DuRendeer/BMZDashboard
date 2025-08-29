<?php
session_start();
require_once 'config.php';

// Verificar se já está logado
if (isset($_COOKIE['bmz_login'])) {
    $loginData = json_decode(base64_decode($_COOKIE['bmz_login']), true);
    if ($loginData && $loginData['expires'] > time()) {
        header('Location: ../index.php');
        exit;
    }
}

// Processar login
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validar credenciais usando o sistema seguro
    if (validateLogin($username, $password)) {
        $loginData = [
            'username' => $username,
            'login_time' => time(),
            'expires' => time() + (30 * 24 * 60 * 60), // 30 dias
            'token' => bin2hex(random_bytes(32))
        ];
        
        $encryptedData = base64_encode(json_encode($loginData));
        setcookie('bmz_login', $encryptedData, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        
        header('Location: ../index.php');
        exit();
    } else {
        $error = 'Credenciais inválidas!';
        error_log("Login failed for user: " . substr($username, 0, 3) . "***");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMZ Login</title>
    <link rel="icon" type="image/x-icon" href="fav.ico">
    <link rel="stylesheet" href="css/login.css">
</head>
<body>
    <div class="container"></div>
    
    <div class="login-content">
        <div class="card">
            <div class="border"></div>
            <div class="bottom-text">BMZ Dashboard</div>
            <div class="content">
                <div class="logo">
                    <svg class="logo1" id="logo-main" viewBox="0 0 24 24">
                        <path d="M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4M12,6A6,6 0 0,0 6,12A6,6 0 0,0 12,18A6,6 0 0,0 18,12A6,6 0 0,0 12,6M12,8A4,4 0 0,1 16,12A4,4 0 0,1 12,16A4,4 0 0,1 8,12A4,4 0 0,1 12,8Z"/>
                    </svg>
                    <svg class="logo2" id="logo-second" viewBox="0 0 24 24">
                        <circle cx="12" cy="12" r="10"/>
                        <circle cx="12" cy="12" r="6"/>
                        <circle cx="12" cy="12" r="2"/>
                    </svg>
                    <div class="trail"></div>
                </div>
                <div class="logo-bottom-text">BMZ</div>
            </div>
        </div>

        <form class="login-form" method="POST">
            <h2>Acesso ao Sistema</h2>
            
            <?php if (isset($error)): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="username">E-mail:</label>
                <input type="email" id="username" name="username" placeholder="Digite seu e-mail" required>
            </div>
            
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" placeholder="Digite sua senha" required>
            </div>
            
            <div class="remember-me">
                <input type="checkbox" id="remember" name="remember" checked>
                <label for="remember">Lembrar por 30 dias</label>
            </div>
            
            <button type="submit" name="login" class="btn">ENTRAR</button>
        </form>
    </div>
</body>
</html>