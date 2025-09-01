<?php
session_start();

if (isset($_SESSION['user_id'])) {
    header("Location: ../../index.php");
    exit();
}

$error_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    include_once '../config/database.php';
    
    $database = new Database();
    $db = $database->getConnection();
    
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $query = "SELECT id, user, senha FROM rh WHERE user = :username";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($password === $user['senha']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['user'];
            $_SESSION['last_activity'] = time();
            $_SESSION['login_time'] = time();
            header("Location: ../../index.php");
            exit();
        } else {
            $error_message = 'Credenciais inválidas!';
        }
    } else {
        $error_message = 'Credenciais inválidas!';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Separador de Holerites</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .top-line {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: #1a365d;
        }

        .logo-container {
            margin-bottom: 40px;
            text-align: center;
        }

        .logo {
            max-width: 150px;
            height: auto;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login {
            color: #000;
            text-transform: uppercase;
            letter-spacing: 2px;
            display: block;
            font-weight: bold;
            font-size: x-large;
        }

        .card {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 350px;
            width: 300px;
            flex-direction: column;
            gap: 25px;
            background: #e3e3e3;
            box-shadow: 16px 16px 32px #c8c8c8, -16px -16px 32px #fefefe;
            border-radius: 8px;
            padding: 30px 20px;
        }

        .inputBox {
            position: relative;
            width: 250px;
        }

        .inputBox input {
            width: 100%;
            padding: 10px;
            outline: none;
            border: none;
            color: #000;
            font-size: 1em;
            background: transparent;
            border-left: 2px solid #000;
            border-bottom: 2px solid #000;
            transition: 0.1s;
            border-bottom-left-radius: 8px;
        }

        .inputBox span {
            margin-top: 5px;
            position: absolute;
            left: 0;
            transform: translateY(-4px);
            margin-left: 10px;
            padding: 10px;
            pointer-events: none;
            font-size: 12px;
            color: #000;
            text-transform: uppercase;
            transition: 0.5s;
            letter-spacing: 3px;
        }

        .inputBox input:valid ~ span,
        .inputBox input:focus ~ span {
            color: #000;
            border: 1px solid #000;
            background: #e3e3e3;
            transform: translateX(25px) translateY(-7px);
            font-size: 0.6em;
            padding: 0 10px;
            border-radius: 2px;
            letter-spacing: 0.5px;
        }

        .inputBox input:valid,
        .inputBox input:focus {
            border: 2px solid #000;
        }

        .enter {
            height: 45px;
            width: 100px;
            border-radius: 5px;
            border: 2px solid #000;
            cursor: pointer;
            background-color: transparent;
            transition: 0.5s;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 2px;
            margin-top: 20px;
            margin-bottom: 0;
        }

        .enter:hover {
            background-color: #000;
            color: white;
        }

        .error {
            color: #dc3545;
            text-align: center;
            margin-top: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="top-line"></div>
    
    <div class="logo-container">
        <img src="../assets/White.png" alt="Logo" class="logo">
    </div>

    <div class="container">
        <div class="card">
            <a class="login">Log in</a>
            
            <form method="POST" action="">
                <div class="inputBox">
                    <input type="text" name="username" required="required">
                    <span class="user">Username</span>
                </div>

                <div class="inputBox">
                    <input type="password" name="password" required="required">
                    <span>Password</span>
                </div>

                <button type="submit" class="enter">Enter</button>
            </form>

            <?php if ($error_message): ?>
                <div class="error"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>