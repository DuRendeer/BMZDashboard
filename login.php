<?php
session_start();

// Verificar se já está logado
if (isset($_COOKIE['bmz_login'])) {
    $loginData = json_decode(base64_decode($_COOKIE['bmz_login']), true);
    if ($loginData && $loginData['expires'] > time()) {
        header('Location: index.php');
        exit;
    }
}

// Processar login
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Debug - remover após teste
    error_log("Login attempt: username='$username', password='$password'");
    
    // Login com as credenciais especificadas
    if ($username === 'gerencia@bmzdashboard.com' && $password === '') {
        $loginData = [
            'username' => $username,
            'login_time' => time(),
            'expires' => time() + (30 * 24 * 60 * 60), // 30 dias
            'token' => bin2hex(random_bytes(32))
        ];
        
        $encryptedData = base64_encode(json_encode($loginData));
        setcookie('bmz_login', $encryptedData, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        
        // Debug
        error_log("Login successful, redirecting...");
        
        header('Location: index.php');
        exit();
    } else {
        $error = 'Credenciais inválidas! Username: ' . $username;
        error_log("Login failed: username='$username'");
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMZ Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            height: 100vh;
            overflow: hidden;
        }

        /* From Uiverse.io by chris_6688 - Background Login */
        .container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: #121212; /* Dark background color */
            background-image: radial-gradient(
                circle at 50% 50%,
                rgba(255, 255, 255, 0.05) 1px,
                transparent 0
            ),
            linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 0),
            linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 0);
            background-size:
                40px 40px,
                20px 20px,
                20px 20px;
            z-index: 1;
            overflow: hidden;
        }

        .container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                45deg,
                transparent,
                transparent 10px,
                rgba(255, 255, 255, 0.03) 10px,
                rgba(255, 255, 255, 0.03) 20px
            );
            z-index: -1;
            opacity: 0.8;
        }

        .container::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(
                circle,
                rgba(255, 255, 255, 0.07) 1px,
                transparent 0
            );
            background-size: 60px 60px;
            z-index: -2;
        }

        /* Login Content */
        .login-content {
            position: relative;
            z-index: 100;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }

        /* From Uiverse.io by Smit-Prajapati - Card Login */
        .card {
            width: 300px;
            height: 200px;
            background: #243137;
            position: relative;
            display: grid;
            place-content: center;
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.5s ease-in-out;
            margin-bottom: 2rem;
        }

        #logo-main, #logo-second {
            height: 100%;
        }

        #logo-main {
            fill: #bd9f67;
        }

        #logo-second {
            padding-bottom: 10px;
            fill: none;
            stroke: #bd9f67;
            stroke-width: 1px;
        }

        .border {
            position: absolute;
            inset: 0px;
            border: 2px solid #bd9f67;
            opacity: 0;
            transform: rotate(10deg);
            transition: all 0.5s ease-in-out;
        }

        .bottom-text {
            position: absolute;
            left: 50%;
            bottom: 13px;
            transform: translateX(-50%);
            font-size: 6px;
            text-transform: uppercase;
            padding: 0px 5px 0px 8px;
            color: #bd9f67;
            background: #243137;
            opacity: 0;
            letter-spacing: 7px;
            transition: all 0.5s ease-in-out;
        }

        .content {
            transition: all 0.5s ease-in-out;
        }

        .content .logo {
            height: 35px;
            position: relative;
            width: 33px;
            overflow: hidden;
            transition: all 1s ease-in-out;
        }

        .content .logo .logo1 {
            height: 33px;
            position: absolute;
            left: 0;
        }

        .content .logo .logo2 {
            height: 33px;
            position: absolute;
            left: 33px;
        }

        .content .logo .trail {
            position: absolute;
            right: 0;
            height: 100%;
            width: 100%;
            opacity: 0;
        }

        .content .logo-bottom-text {
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
            margin-top: 30px;
            color: #bd9f67;
            padding-left: 8px;
            font-size: 11px;
            opacity: 0;
            letter-spacing: none;
            transition: all 0.5s ease-in-out 0.5s;
        }

        .card:hover {
            border-radius: 0;
            transform: scale(1.1);
        }

        .card:hover .logo {
            width: 134px;
            animation: opacity 1s ease-in-out;
        }

        .card:hover .border {
            inset: 15px;
            opacity: 1;
            transform: rotate(0);
        }

        .card:hover .bottom-text {
            letter-spacing: 3px;
            opacity: 1;
            transform: translateX(-50%);
        }

        .card:hover .content .logo-bottom-text {
            opacity: 1;
            letter-spacing: 9.5px;
        }

        .card:hover .trail {
            animation: trail 1s ease-in-out;
        }

        @keyframes opacity {
            0% {
                border-right: 1px solid transparent;
            }
            10% {
                border-right: 1px solid #bd9f67;
            }
            80% {
                border-right: 1px solid #bd9f67;
            }
            100% {
                border-right: 1px solid transparent;
            }
        }

        @keyframes trail {
            0% {
                background: linear-gradient(90deg, rgba(189, 159, 103, 0) 90%, rgb(189, 159, 103) 100%);
                opacity: 0;
            }
            30% {
                background: linear-gradient(90deg, rgba(189, 159, 103, 0) 70%, rgb(189, 159, 103) 100%);
                opacity: 1;
            }
            70% {
                background: linear-gradient(90deg, rgba(189, 159, 103, 0) 70%, rgb(189, 159, 103) 100%);
                opacity: 1;
            }
            95% {
                background: linear-gradient(90deg, rgba(189, 159, 103, 0) 90%, rgb(189, 159, 103) 100%);
                opacity: 0;
            }
        }

        /* Login Form */
        .login-form {
            background: rgba(36, 49, 55, 0.95);
            padding: 2rem;
            border-radius: 15px;
            border: 2px solid #bd9f67;
            backdrop-filter: blur(10px);
            min-width: 350px;
        }

        .login-form h2 {
            color: #bd9f67;
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.8rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #bd9f67;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 0.8rem;
            border: 2px solid #bd9f67;
            border-radius: 8px;
            background: rgba(18, 18, 18, 0.8);
            color: #fff;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #fff;
            box-shadow: 0 0 10px rgba(189, 159, 103, 0.5);
        }

        /* From Uiverse.io by CristianMontoya98 - Button Styles */
        .btn {
            width: 100%;
            height: 2.3em;
            margin: 0.5em 0;
            background: black;
            color: white;
            border: none;
            border-radius: 0.625em;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            position: relative;
            z-index: 1;
            overflow: hidden;
        }

        .btn:hover {
            color: black;
        }

        .btn:after {
            content: "";
            background: white;
            position: absolute;
            z-index: -1;
            left: -20%;
            right: -20%;
            top: 0;
            bottom: 0;
            transform: skewX(-45deg) scale(0, 1);
            transition: all 0.5s;
        }

        .btn:hover:after {
            transform: skewX(-45deg) scale(1, 1);
            -webkit-transition: all 0.5s;
            transition: all 0.5s;
        }

        .error {
            color: #ff4757;
            text-align: center;
            margin-bottom: 1rem;
            font-weight: bold;
        }

        .remember-me {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .remember-me input[type="checkbox"] {
            margin-right: 0.5rem;
            width: auto;
        }

        .remember-me label {
            color: #bd9f67;
            margin: 0;
        }

        @media (max-width: 768px) {
            .card {
                width: 250px;
                height: 150px;
            }

            .login-form {
                min-width: 300px;
                padding: 1.5rem;
            }

            .btn {
                font-size: 18px;
            }
        }
    </style>
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
                <input type="email" id="username" name="username" value="gerencia@bmzdashboard.com" required>
            </div>
            
            <div class="form-group">
                <label for="password">Senha:</label>
                <input type="password" id="password" name="password" value="" required>
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