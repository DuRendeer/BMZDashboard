<?php
session_start();

// Verificar se está logado
$isLoggedIn = false;
$username = '';

if (isset($_COOKIE['bmz_login'])) {
    $loginData = json_decode(base64_decode($_COOKIE['bmz_login']), true);
    if ($loginData && $loginData['expires'] > time()) {
        $isLoggedIn = true;
        $username = $loginData['username'];
    }
}

// Se não estiver logado, redirecionar para login
if (!$isLoggedIn) {
    header('Location: assets/login.php');
    exit;
}

// Processar logout
if (isset($_GET['logout'])) {
    setcookie('bmz_login', '', time() - 3600, '/');
    header('Location: assets/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMZ Menu Utilidades</title>
    <link rel="icon" type="image/x-icon" href="assets/fav.ico">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/CircularText.css">
</head>
<body>
    <div class="container"></div>
    
    <div class="main-content">
        <div style="position: relative; display: flex; flex-direction: column; align-items: center; margin-bottom: 3rem;">
            <img src="assets/Utilidades Redondo.png" alt="BMZ Logo" style="width: 250px; height: 250px; object-fit: contain; margin-bottom: 1rem; border-radius: 50%; box-shadow: 0 0 20px rgba(189, 159, 103, 0.3);">
            <div id="circular-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 250px; height: 250px; border-radius: 50%; pointer-events: none;"></div>
        </div>
        
        <div class="button-container">
            <a href="https://bmzdashboard.shop/music-player/" class="brutalist-button api-music">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M12 3v10.55c-.59-.34-1.27-.55-2-.55-2.21 0-4 1.79-4 4s1.79 4 4 4 4-1.79 4-4V7h4V3h-6z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>API de</span>
                    <span>MÚSICAS</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/Mapeamento/" class="brutalist-button mapping">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M15 11V5l-3-3-3 3v2H3v14h18V11h-6zm-8 8H5v-2h2v2zm0-4H5v-2h2v2zm0-4H5V9h2v2zm6 8h-2v-2h2v2zm0-4h-2v-2h2v2zm0-4h-2V9h2v2zm0-4h-2V5h2v2zm6 12h-2v-2h2v2zm0-4h-2v-2h2v2z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Mapeamento</span>
                    <span>ESCRITÓRIO</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/Sincronizacao/" class="brutalist-button sync">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M12 4V1L8 5l4 4V6c3.31 0 6 2.69 6 6 0 1.01-.25 1.97-.7 2.8l1.46 1.46C19.54 15.03 20 13.57 20 12c0-4.42-3.58-8-8-8zm0 14c-3.31 0-6-2.69-6-6 0-1.01.25-1.97.7-2.8L5.24 7.74C4.46 8.97 4 10.43 4 12c0 4.42 3.58 8 8 8v3l4-4-4-4v3z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Sincronização</span>
                    <span>DADOS</span>
                </div>
            </a>

            <a href="https://www.bmzdashboard.shop/dica-anonima/" class="brutalist-button tips">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Dicas</span>
                    <span>ANÔNIMAS</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/Dashboard-Operacional/" class="brutalist-button dashboard">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Dashboard</span>
                    <span>OPERACIONAL</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/Form-Anonimo" class="brutalist-button form-anonimo">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zm-3-7v1H9v-1h6zm0-2v1H9V9h6zm-2-4V5l4 4h-4z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Form</span>
                    <span>ANÔNIMO</span>
                </div>
            </a>
        </div>
        
        <div class="button-container">
            <a href="https://bmzdashboard.shop/Emails/" class="brutalist-button emails">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Sistema de</span>
                    <span>EMAILS</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/roletaSorteio/" class="brutalist-button roleta">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Roleta</span>
                    <span>SORTEIO</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/Calc-Aux-Acidente/" class="brutalist-button calc-aux">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-5 14H7v-2h7v2zm3-4H7v-2h10v2zm0-4H7V7h10v2z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Calc Aux</span>
                    <span>ACIDENTE</span>
                </div>
            </a>

            <a href="https://bmzdashboard.shop/Extrato/" class="brutalist-button extrato">
                <div class="button-logo">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" class="button-icon">
                        <path fill="#ffffff" d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm4 18H6V4h7v5h5v11zm-1-7v-4H9v1h3v1H9v1h3v1H9v1h6z"/>
                    </svg>
                </div>
                <div class="button-text">
                    <span>Sistema de</span>
                    <span>EXTRATO</span>
                </div>
            </a>
        </div>
    </div>

    <div aria-label="Orange and tan hamster running in a metal wheel" role="img" class="wheel-and-hamster">
        <div class="wheel"></div>
        <div class="hamster">
            <div class="hamster__body">
                <div class="hamster__head">
                    <div class="hamster__ear"></div>
                    <div class="hamster__eye"></div>
                    <div class="hamster__nose"></div>
                </div>
                <div class="hamster__limb hamster__limb--fr"></div>
                <div class="hamster__limb hamster__limb--fl"></div>
                <div class="hamster__limb hamster__limb--br"></div>
                <div class="hamster__limb hamster__limb--bl"></div>
                <div class="hamster__tail"></div>
            </div>
        </div>
        <div class="spoke"></div>
    </div>

    <!-- Logout Container -->
    <div class="logout-container">
        <div class="user-info">Bem-vindo, <?= htmlspecialchars($username) ?></div>
        <button onclick="logout()" class="logout-btn">SAIR</button>
    </div>

    <script src="assets/js/index.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const circularTextContainer = document.getElementById('circular-text');
            const text = 'BMZ*MENU*UTILIDADES*';
            const letters = Array.from(text);
            
            // Criar as letras (SEM rotação do container, só as letras giram)
            letters.forEach((letter, i) => {
                const span = document.createElement('span');
                span.textContent = letter;
                span.style.position = 'absolute';
                span.style.fontSize = '24px';
                span.style.color = '#bd9f67';
                span.style.fontWeight = '900';
                span.style.left = '0';
                span.style.right = '0';
                span.style.top = '0';
                span.style.bottom = '0';
                span.style.transition = 'all 0.5s cubic-bezier(0, 0, 0, 1)';
                
                const rotationDeg = (360 / letters.length) * i;
                const factor = Math.PI / letters.length;
                const x = factor * i;
                const y = factor * i;
                const transform = `rotateZ(${rotationDeg}deg) translate3d(${x}px, ${y}px, 0)`;
                
                span.style.transform = transform;
                span.style.webkitTransform = transform;
                
                circularTextContainer.appendChild(span);
            });
            
            // Animação suave APENAS das letras (sem rotação do container)
            let rotation = 0;
            function animate() {
                rotation += 0.5;
                letters.forEach((letter, i) => {
                    const span = circularTextContainer.children[i];
                    if (span) {
                        const rotationDeg = (360 / letters.length) * i + rotation;
                        const factor = Math.PI / letters.length;
                        const x = factor * i;
                        const y = factor * i;
                        const transform = `rotateZ(${rotationDeg}deg) translate3d(${x}px, ${y}px, 0)`;
                        span.style.transform = transform;
                        span.style.webkitTransform = transform;
                    }
                });
                requestAnimationFrame(animate);
            }
            animate();
            
        });
    </script>
</body>
</html>