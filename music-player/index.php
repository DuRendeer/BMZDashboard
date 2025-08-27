<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BMZ Music Player</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app">
        <header class="header">
            <div class="logo-container">
                <img src="black.png" alt="BMZ Logo" class="logo">
                <h1>BMZ Music Player</h1>
            </div>
            <div class="status-indicator">
                <div class="status-dot" id="status-dot"></div>
                <span id="status">Conectando...</span>
            </div>
        </header>

        <main class="main-content">
            <div class="player-card">
                <div class="message-container">
                    <div id="current-message" class="message-display">
                        Aguardando comando...
                    </div>
                </div>

                <div class="music-info">
                    <div id="current-song" class="song-title">
                        Nenhuma música selecionada
                    </div>
                </div>

                <div class="player-controls">
                    <audio id="audio-player" controls preload="metadata" autoplay>
                        Seu navegador não suporta o elemento audio.
                    </audio>
                </div>

                <button id="manual-play-btn" class="play-button" style="display: none;">
                    <span id="play-btn-text">▶️ Tocar Música</span>
                </button>
                
                <div class="radio-input" id="autoplay-controls">
                    <label class="label">
                        <div class="back-side"></div>
                        <input type="radio" id="autoplay-off" name="autoplay-radio" value="off" checked />
                        <span class="text">OFF</span>
                        <span class="bottom-line"></span>
                    </label>

                    <label class="label">
                        <div class="back-side"></div>
                        <input type="radio" id="autoplay-on" name="autoplay-radio" value="on" />
                        <span class="text">ON</span>
                        <span class="bottom-line"></span>
                    </label>
                </div>
            </div>
        </main>

        <!-- Histórico no canto direito -->
        <div id="history-panel" class="history-panel">
            <div class="card">
                <div class="card-header">
                    <h3>📊 Histórico</h3>
                    <div class="card-actions">
                        <button id="clear-logs-btn" class="btn-clear">🗑️</button>
                        <button id="toggle-history-btn" class="btn-toggle">−</button>
                    </div>
                </div>
                <div id="logs-container" class="card-body">
                    <div class="log-empty">Nenhum registro</div>
                </div>
            </div>
        </div>
    </div>

    <script src="js/player.js"></script>
</body>
</html>