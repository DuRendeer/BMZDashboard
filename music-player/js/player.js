class MusicPlayer {
    constructor() {
        this.audioPlayer = document.getElementById('audio-player');
        this.currentMessage = document.getElementById('current-message');
        this.currentSong = document.getElementById('current-song');
        this.status = document.getElementById('status');
        this.lastTimestamp = null;
        
        this.init();
    }
    
    init() {
        this.setStatus('🟢 Online');
        this.setupActivateButton();
        this.setupClearLogsButton();
        this.enableAutoplay();
        this.startPolling();
        this.loadLogs();
    }
    
    setupActivateButton() {
        // Função removida - controle agora é via radio buttons
    }
    
    enableAutoplay() {
        // Preparar contexto de áudio
        document.addEventListener('click', () => {
            this.audioPlayer.muted = false;
        }, { once: true });
        
        // Auto-interação invisível para habilitar autoplay
        setTimeout(() => {
            this.audioPlayer.play().catch(() => {
                console.log('Preparing audio context...');
            });
            this.audioPlayer.pause();
        }, 100);
    }
    
    setStatus(statusText) {
        this.status.textContent = statusText;
        const statusDot = document.getElementById('status-dot');
        
        if (statusText.includes('Online')) {
            statusDot.classList.add('online');
        } else {
            statusDot.classList.remove('online');
        }
    }
    
    startPolling() {
        setInterval(() => {
            this.checkForNewCommands();
        }, 2000);
    }
    
    async checkForNewCommands() {
        try {
            const response = await fetch('api/status.php');
            const data = await response.json();
            
            if (data.success && data.data) {
                const commandData = data.data;
                
                if (commandData.timestamp && commandData.timestamp !== this.lastTimestamp) {
                    this.playMusic(commandData);
                    this.lastTimestamp = commandData.timestamp;
                } else if (!commandData.timestamp) {
                    this.showMessage(commandData.message);
                }
            }
            
            this.setStatus('🟢 Online');
            
        } catch (error) {
            console.error('Error checking for commands:', error);
            this.setStatus('🔴 Offline - Erro de conexão');
        }
    }
    
    // Função original playMusic movida para cima
    
    forceAutoplay(commandData) {
        if (!this.autoplayEnabled) {
            console.log('⚠️ Autoplay não ativado pelo usuário');
            this.showPlayButton(commandData);
            return;
        }
        
        // Tentar reprodução direta se autoplay foi ativado
        setTimeout(() => {
            this.audioPlayer.play().then(() => {
                console.log('🎵 Autoplay funcionou! Música iniciada:', commandData.music_name);
                this.showMessage(`🎵 Reproduzindo: ${commandData.music_name}`, true);
            }).catch((error) => {
                console.warn('Autoplay falhou mesmo ativado:', error.message);
                this.showPlayButton(commandData);
            });
        }, 300);
    }

    showPlayButton(commandData) {
        const playBtn = document.getElementById('manual-play-btn');
        const playBtnText = document.getElementById('play-btn-text');
        
        playBtnText.textContent = `▶️ Tocar: ${commandData.music_name}`;
        playBtn.style.display = 'block';
        playBtn.classList.add('pulse');
        
        playBtn.onclick = () => {
            this.audioPlayer.play().then(() => {
                console.log('Music started playing after user interaction');
                this.showMessage(`🎵 Reproduzindo: ${commandData.music_name}`, true);
                playBtn.style.display = 'none';
            }).catch((error) => {
                console.error('Error playing music:', error);
                this.showMessage(`❌ Erro: ${error.message}`);
            });
        };
    }
    
    tryFallbackUrl(fallbackUrl, musicName) {
        console.log('Trying fallback URL:', fallbackUrl);
        this.audioPlayer.src = fallbackUrl;
        this.audioPlayer.load();
        
        this.audioPlayer.play().then(() => {
            console.log('Fallback URL worked');
            this.showMessage(`▶️ Reproduzindo: ${musicName}`, true);
        }).catch((error) => {
            console.error('Fallback also failed:', error);
            this.showMessage(`❌ Arquivo não encontrado: ${musicName}`);
        });
    }
    
    showMessage(message, isActive = false) {
        this.currentMessage.textContent = message;
        this.currentMessage.classList.remove('active');
        
        if (isActive) {
            this.currentMessage.classList.add('active');
        }
    }

    setupClearLogsButton() {
        const clearBtn = document.getElementById('clear-logs-btn');
        const toggleBtn = document.getElementById('toggle-history-btn');
        const historyPanel = document.getElementById('history-panel');
        const cardBody = document.querySelector('.card-body');

        // Botão limpar logs
        clearBtn.onclick = async () => {
            try {
                await fetch('api/logs.php', { method: 'DELETE' });
                this.loadLogs();
            } catch (error) {
                console.error('Error clearing logs:', error);
            }
        };

        // Botão toggle para minimizar/expandir
        let isMinimized = false;
        toggleBtn.onclick = () => {
            isMinimized = !isMinimized;
            if (isMinimized) {
                cardBody.style.display = 'none';
                toggleBtn.textContent = '+';
            } else {
                cardBody.style.display = 'block';
                toggleBtn.textContent = '−';
            }
        };

        // Setup controles de autoplay
        const autoplayOn = document.getElementById('autoplay-on');
        const autoplayOff = document.getElementById('autoplay-off');
        
        // Verificar estado salvo
        const isActivated = localStorage.getItem('autoplay-activated') === 'true';
        if (isActivated) {
            autoplayOn.checked = true;
            this.autoplayEnabled = true;
        }

        // Event listeners para mudança
        autoplayOn.onchange = () => {
            if (autoplayOn.checked) {
                this.activateAutoplay();
            }
        };

        autoplayOff.onchange = () => {
            if (autoplayOff.checked) {
                this.autoplayEnabled = false;
                localStorage.setItem('autoplay-activated', 'false');
            }
        };
    }

    activateAutoplay() {
        const testAudio = new Audio();
        testAudio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAAC';
        testAudio.volume = 0.01;
        testAudio.play().then(() => {
            localStorage.setItem('autoplay-activated', 'true');
            this.autoplayEnabled = true;
            console.log('🔊 Autoplay ativado!');
        }).catch(() => {
            document.getElementById('autoplay-off').checked = true;
            console.log('Erro ao ativar autoplay');
        });
    }

    async loadLogs() {
        try {
            const response = await fetch('api/logs.php');
            const data = await response.json();
            
            if (data.success) {
                this.displayLogs(data.data);
            }
        } catch (error) {
            console.error('Error loading logs:', error);
        }
    }

    displayLogs(logs) {
        const container = document.getElementById('logs-container');
        
        if (!logs || logs.length === 0) {
            container.innerHTML = '<div class="log-empty">Nenhum registro</div>';
            return;
        }

        container.innerHTML = logs.map(log => `
            <div class="log-item">
                <div class="log-header">
                    <span class="log-closer">${log.closer}</span>
                    <span class="log-time">${log.date}</span>
                </div>
                <div class="log-music">🎵 ${log.music_name}</div>
                <div class="log-message">"${log.message}"</div>
            </div>
        `).join('');
    }

    playMusic(commandData) {
        this.showMessage(commandData.message, true);
        
        this.currentSong.textContent = `🎵 ${commandData.music_name}`;
        this.currentSong.classList.add('fade-in');
        
        if (commandData.music_url) {
            console.log('🎵 Tentando carregar:', commandData.music_url);
            this.audioPlayer.src = commandData.music_url;
            this.audioPlayer.load();
            
            // Forçar reprodução automática
            this.forceAutoplay(commandData);
        }
        
        // Recarregar logs após nova música
        setTimeout(() => {
            this.loadLogs();
        }, 1000);
        
        setTimeout(() => {
            this.currentSong.classList.remove('fade-in');
        }, 500);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Simular interação automática ao carregar página
    setTimeout(() => {
        // Clique invisível automático
        const autoClick = new MouseEvent('click', {
            view: window,
            bubbles: true,
            cancelable: true,
            clientX: 1,
            clientY: 1
        });
        document.body.dispatchEvent(autoClick);
        console.log('🤖 Auto-interação simulada');
    }, 500);
    
    // Forçar contexto de áudio ativo
    const silentAudio = new Audio();
    silentAudio.src = 'data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LHfSgELYPL9OWnWw0DUK7r8q1hEAlMq9/ns1YSB0Ok2+6vcCAVJ2GG7Mtp';
    silentAudio.volume = 0;
    silentAudio.play().catch(() => {});
    
    new MusicPlayer();
});