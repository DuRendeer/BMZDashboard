// 🏢 Base de dados dos colaboradores BMZ
const employees = [
    { name: "Adriano Kolitski", photo: "assets/colaboradores/ADRIANO KOLITSKI.jpg" },
    { name: "Eduardo Sochodolak", photo: "assets/colaboradores/EDUARDO SOCHODOLAK.jpg" },
    { name: "Leonardo Marconato", photo: "assets/colaboradores/Leonardo M.png" },
    { name: "Alexander Nicolas Costa", photo: "assets/colaboradores/ALEXANDER NICOLAS COSTA.jpg"},
    { name: "Bruna Aparecida Lukaski", photo: "assets/colaboradores/BRUNA APARECIDA LUKASKI.jpg" },
    { name: "Camile Nunes", photo: "assets/colaboradores/CAMILE NUNES.jpg" },
    { name: "Rafael boa pergunta", photo: "assets/colaboradores/Rafael.png" },
    { name: "Gabriely Holodivski", photo: "assets/colaboradores/GABRIELY HOLODIVSKI.jpg" },
    { name: "Henrique Gerei", photo: "assets/colaboradores/Henrique Gerei.png" },
    { name: "Hevilin Vitória Machado", photo: "assets/colaboradores/HEVILIN VITÓRIA MACHADO.jpg" },
    { name: "Jaqueline Papirniak", photo: "assets/colaboradores/JAQUELINE PAPIRNIAK.jpg" },
    { name: "Liedson 30tou", photo: "assets/colaboradores/Liedson.png" },
    { name: "Karen Sochodolak", photo: "assets/colaboradores/KAREN SOCHODOLAK.jpg" },
    { name: "Maisa Bail", photo: "assets/colaboradores/MAISA BAIL.jpg" },
    { name: "Margarete Dorak", photo: "assets/colaboradores/MARGARETE DORAK.jpg" },
    { name: "Rodrigo Garbachevski", photo: "assets/colaboradores/RODRIGO GARBACHEVSKI.jpg" },
    { name: "Henrique Leite", photo: "assets/colaboradores/HENRIQUE LEITE.jpg" },
    { name: "Thamires Andrade", photo: "assets/colaboradores/THAMIRES ANDRADE.jpg" },
    { name: "Jamille C. Scheidt", photo: "assets/colaboradores/JAMILLE C. SCHEIDT.jpg" },
    { name: "Jéssica Riffel", photo: "assets/colaboradores/JESSICA RIFFEL.png" },
    { name: "Gisele Saplak", photo: "assets/colaboradores/GISELE SAPLAK.jpg" },
    { name: "Delia Ochoa", photo: "assets/colaboradores/DELIA OCHOA.jpg" },
    { name: "Kélita Schulz", photo: "assets/colaboradores/KÉLITA SCHULZ.jpg" },
    { name: "Maria Leticia", photo: "assets/colaboradores/MARIA LETICIA.png" },
    { name: "Lucas Racelli", photo: "assets/colaboradores/LUCAS RACELLI.jpg" }
];

let selectedEmployees = [];
let prizes = [];
let participants = [];
let isSpinning = false;
let currentRotation = 0;
let prizeHistory = [];

// 🔐 CONFIGURAÇÃO SEGURA DO WEBHOOK
let discordWebhookURL = localStorage.getItem('discordWebhook') || '';

function configureDiscordWebhook() {
    const webhook = prompt('🔐 Cole a URL do webhook do Discord:\n(Esta informação ficará salva localmente no seu navegador)', discordWebhookURL);
    
    if (webhook && webhook.trim()) {
        if (webhook.includes('discord.com/api/webhooks/')) {
            discordWebhookURL = webhook.trim();
            localStorage.setItem('discordWebhook', discordWebhookURL);
            alert('✅ Webhook configurado com sucesso!');
            updateDiscordButton();
        } else {
            alert('❌ URL inválida! Deve ser um webhook do Discord.');
        }
    }
}

function updateDiscordButton() {
    const btn = document.getElementById('discordBtn');
    if (discordWebhookURL) {
        btn.textContent = '📤 Exportar para Discord';
        btn.disabled = false;
        btn.title = 'Webhook configurado';
    } else {
        btn.textContent = '🔧 Configurar Discord';
        btn.disabled = false;
        btn.title = 'Clique para configurar o webhook';
    }
}

// Cores minimalistas elegantes
const colors = [
    '#E74C3C', '#3498DB', '#2ECC71', '#F39C12', '#9B59B6',
    '#1ABC9C', '#E67E22', '#34495E', '#F1C40F', '#E91E63',
    '#8E44AD', '#16A085', '#D35400', '#2980B9', '#27AE60'
];

const canvas = document.getElementById('wheelCanvas');
const ctx = canvas.getContext('2d');
const fullscreenCanvas = document.getElementById('fullscreenCanvas');
const fullscreenCtx = fullscreenCanvas.getContext('2d');

// 🔊 SISTEMA DE ÁUDIO MELHORADO COM MÚLTIPLAS OPÇÕES
let audioContext;
let soundEnabled = true;
let spinAudio = null;
let victoryAudio = null;
let spinSoundOptions = [
    { name: 'Opção 1', file: 'assets/sons/Opcao1.mp3', duration: 11 },
    { name: 'Opção 2', file: 'assets/sons/Opcao2.mp3', duration: 11 },
    { name: 'Opção 3', file: 'assets/sons/Opcao3.mp3', duration: 5 },
    { name: 'Opção 4', file: 'assets/sons/Opcao4.mp3', duration: 8 }
];
let currentSpinSound = 0; // Índice do som atual
let debugMode = false;
let logoClickCount = 0;
let logoClickTimer = null;
let employeeOrder = 'random'; // 'random', 'alphabetic', 'reverse'
let nextEmployeeName = '';
let randomSoundEnabled = false; // Som aleatório a cada giro

function initAudio() {
    try {
        audioContext = new (window.AudioContext || window.webkitAudioContext)();
        
        // 🎵 Carregar áudios de giro
        loadSpinAudios();
        
        // 🏆 Carregar som de vitória
        victoryAudio = new Audio('assets/sons/Vitoria.mp3');
        victoryAudio.volume = 0.8;
        victoryAudio.preload = 'auto';
        
        // Debug para áudio de vitória
        victoryAudio.addEventListener('loadeddata', () => {
            console.log('✅ Vitoria.mp3 carregado com sucesso');
        });
        
        victoryAudio.addEventListener('error', (error) => {
            console.log('❌ Erro ao carregar Vitoria.mp3:', error);
            victoryAudio = null;
        });
        
    } catch (e) {
        if (debugMode) console.log('Áudio não suportado neste navegador');
    }
}

// 🎵 Carregar todos os áudios de giro
let spinAudios = [];

function loadSpinAudios() {
    spinSoundOptions.forEach((option, index) => {
        if (option.file) {
            const audio = new Audio(option.file);
            audio.volume = 0.7;
            audio.preload = 'auto';
            
            audio.addEventListener('loadeddata', () => {
                console.log(`✅ ${option.file} carregado com sucesso`);
            });
            
            audio.addEventListener('error', (error) => {
                console.log(`❌ Erro ao carregar ${option.file}:`, error);
                spinAudios[index] = null;
            });
            
            spinAudios[index] = audio;
        } else {
            spinAudios[index] = null;
        }
    });
}

function playSpinSound() {
    if (!soundEnabled) return;
    
    // Se som aleatório está ativo, escolher um som aleatório
    let soundIndex = currentSpinSound;
    if (randomSoundEnabled) {
        soundIndex = Math.floor(Math.random() * spinSoundOptions.length);
        if (debugMode) console.log(`🎲 Som aleatório selecionado: ${spinSoundOptions[soundIndex].name}`);
    }
    
    const selectedAudio = spinAudios[soundIndex];
    
    // Se tem arquivo MP3, tocar o arquivo
    if (selectedAudio) {
        try {
            selectedAudio.currentTime = 0;
            selectedAudio.play().catch(error => {
                if (debugMode) console.log('Erro ao reproduzir áudio:', error);
                playFallbackSpinSound();
            });
        } catch (error) {
            if (debugMode) console.log('Erro no áudio MP3:', error);
            playFallbackSpinSound();
        }
    } else {
        // Fallback para som sintético
        playFallbackSpinSound();
    }
    
    // Retornar o índice usado para cálculo de duração
    return soundIndex;
}

// 🔄 ÁUDIO SINTÉTICO COMO FALLBACK
function playFallbackSpinSound() {
    if (!soundEnabled || !audioContext) return;
    
    console.log('🔄 Usando áudio sintético (fallback)');
    
    // Som dramático de suspense sintético
    let tickCount = 0;
    const totalTicks = 100; // Mais ticks para 10 segundos
    
    // Som de fundo tensão crescente
    const backgroundOsc = audioContext.createOscillator();
    const backgroundGain = audioContext.createGain();
    
    backgroundOsc.connect(backgroundGain);
    backgroundGain.connect(audioContext.destination);
    
    backgroundOsc.frequency.setValueAtTime(55, audioContext.currentTime);
    backgroundOsc.frequency.linearRampToValueAtTime(110, audioContext.currentTime + 10);
    backgroundGain.gain.setValueAtTime(0.02, audioContext.currentTime);
    backgroundGain.gain.linearRampToValueAtTime(0.05, audioContext.currentTime + 8);
    backgroundGain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 10);
    
    backgroundOsc.type = 'sawtooth';
    backgroundOsc.start(audioContext.currentTime);
    backgroundOsc.stop(audioContext.currentTime + 10);
    
    function createDramaticTick() {
        if (tickCount >= totalTicks) return;
        
        // Tick de suspense crescente
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        const filter = audioContext.createBiquadFilter();
        
        oscillator.connect(filter);
        filter.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        // Frequência que aumenta com a tensão
        const progress = tickCount / totalTicks;
        const baseFreq = 600 + (progress * 600); // 600Hz a 1200Hz
        
        oscillator.frequency.setValueAtTime(baseFreq, audioContext.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(baseFreq * 0.7, audioContext.currentTime + 0.08);
        
        // Filtro para dar brilho
        filter.type = 'highpass';
        filter.frequency.setValueAtTime(300, audioContext.currentTime);
        
        // Volume que cresce com a tensão
        const volume = 0.02 + (progress * 0.03);
        gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.08);
        
        oscillator.type = 'square';
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.08);
        
        tickCount++;
        
        // Intervalo que diminui criando aceleração dramática (10 segundos total)
        let interval;
        if (progress < 0.4) {
            interval = 120 - (progress * 40); // 120ms para 80ms
        } else if (progress < 0.8) {
            interval = 80 - ((progress - 0.4) * 50); // 80ms para 30ms
        } else {
            // Final super rápido
            const finalProgress = (progress - 0.8) / 0.2;
            interval = 30 - (finalProgress * 20); // 30ms para 10ms
        }
        
        setTimeout(createDramaticTick, interval);
    }
    
    // Começar após pequeno delay
    setTimeout(createDramaticTick, 300);
}

// 🎵 VARIAÇÕES DE SONS SINTÉTICOS PARA ROLETA
function playSpinSoundVariation(variation) {
    if (!soundEnabled || !audioContext) return;
    
    switch(variation) {
        case 0: // Show do Milhão (fallback sintético)
            playFallbackSpinSound();
            break;
        case 1: // Suspense Clássico
            playClassicSuspenseSound();
            break;
        case 2: // Roda da Fortuna
            playWheelOfFortuneSound();
            break;
        case 3: // Casino Royal
            playCasinoRoyalSound();
            break;
        default:
            playFallbackSpinSound();
    }
}

function playClassicSuspenseSound() {
    // Som de suspense mais simples e clássico
    let tickCount = 0;
    const totalTicks = 80;
    
    function createClassicTick() {
        if (tickCount >= totalTicks) return;
        
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        const progress = tickCount / totalTicks;
        const baseFreq = 400 + (progress * 400);
        
        oscillator.frequency.setValueAtTime(baseFreq, audioContext.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(baseFreq * 0.8, audioContext.currentTime + 0.05);
        
        const volume = 0.015 + (progress * 0.02);
        gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.05);
        
        oscillator.type = 'triangle';
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.05);
        
        tickCount++;
        
        const interval = 100 - (progress * 60); // 100ms para 40ms
        setTimeout(createClassicTick, interval);
    }
    
    setTimeout(createClassicTick, 200);
}

function playWheelOfFortuneSound() {
    // Som similar ao programa Roda da Fortuna
    let tickCount = 0;
    const totalTicks = 120;
    
    // Som de fundo suave
    const bgOsc = audioContext.createOscillator();
    const bgGain = audioContext.createGain();
    
    bgOsc.connect(bgGain);
    bgGain.connect(audioContext.destination);
    
    bgOsc.frequency.setValueAtTime(150, audioContext.currentTime);
    bgOsc.frequency.linearRampToValueAtTime(300, audioContext.currentTime + 10);
    bgGain.gain.setValueAtTime(0.01, audioContext.currentTime);
    bgGain.gain.linearRampToValueAtTime(0.03, audioContext.currentTime + 8);
    bgGain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 10);
    
    bgOsc.type = 'sine';
    bgOsc.start(audioContext.currentTime);
    bgOsc.stop(audioContext.currentTime + 10);
    
    function createWheelTick() {
        if (tickCount >= totalTicks) return;
        
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        
        oscillator.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        const progress = tickCount / totalTicks;
        const baseFreq = 800 + (progress * 400);
        
        oscillator.frequency.setValueAtTime(baseFreq, audioContext.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(baseFreq * 0.6, audioContext.currentTime + 0.06);
        
        const volume = 0.02 + (progress * 0.015);
        gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.06);
        
        oscillator.type = 'sawtooth';
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.06);
        
        tickCount++;
        
        let interval;
        if (progress < 0.6) {
            interval = 90 - (progress * 30); // 90ms para 60ms
        } else {
            interval = 60 - ((progress - 0.6) * 40); // 60ms para 20ms
        }
        
        setTimeout(createWheelTick, interval);
    }
    
    setTimeout(createWheelTick, 300);
}

function playCasinoRoyalSound() {
    // Som premium estilo casino
    let tickCount = 0;
    const totalTicks = 150;
    
    // Acordes de fundo elegantes
    const chords = [
        [261.63, 329.63, 392.00], // C major
        [293.66, 369.99, 440.00], // D major
        [329.63, 415.30, 493.88]  // E major
    ];
    
    chords.forEach((chord, index) => {
        setTimeout(() => {
            chord.forEach(freq => {
                const osc = audioContext.createOscillator();
                const gain = audioContext.createGain();
                
                osc.connect(gain);
                gain.connect(audioContext.destination);
                
                osc.frequency.setValueAtTime(freq, audioContext.currentTime);
                gain.gain.setValueAtTime(0.008, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 3);
                
                osc.type = 'sine';
                osc.start(audioContext.currentTime);
                osc.stop(audioContext.currentTime + 3);
            });
        }, index * 2500);
    });
    
    function createCasinoTick() {
        if (tickCount >= totalTicks) return;
        
        const oscillator = audioContext.createOscillator();
        const gainNode = audioContext.createGain();
        const filter = audioContext.createBiquadFilter();
        
        oscillator.connect(filter);
        filter.connect(gainNode);
        gainNode.connect(audioContext.destination);
        
        const progress = tickCount / totalTicks;
        const baseFreq = 1000 + (progress * 800);
        
        oscillator.frequency.setValueAtTime(baseFreq, audioContext.currentTime);
        oscillator.frequency.exponentialRampToValueAtTime(baseFreq * 0.7, audioContext.currentTime + 0.04);
        
        filter.type = 'bandpass';
        filter.frequency.setValueAtTime(baseFreq, audioContext.currentTime);
        filter.Q.setValueAtTime(5, audioContext.currentTime);
        
        const volume = 0.025 + (progress * 0.02);
        gainNode.gain.setValueAtTime(volume, audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.04);
        
        oscillator.type = 'square';
        oscillator.start(audioContext.currentTime);
        oscillator.stop(audioContext.currentTime + 0.04);
        
        tickCount++;
        
        let interval;
        if (progress < 0.5) {
            interval = 80 - (progress * 20); // 80ms para 60ms
        } else if (progress < 0.8) {
            interval = 60 - ((progress - 0.5) * 33); // 60ms para 40ms
        } else {
            interval = 40 - ((progress - 0.8) * 25); // 40ms para 15ms
        }
        
        setTimeout(createCasinoTick, interval);
    }
    
    setTimeout(createCasinoTick, 400);
}

function playWinSound() {
    console.log('🏆 playWinSound chamado');
    console.log('🔊 soundEnabled:', soundEnabled);
    console.log('🎵 victoryAudio:', victoryAudio);
    
    if (!soundEnabled) {
        console.log('❌ Som desabilitado');
        return;
    }
    
    if (!victoryAudio) {
        console.log('❌ victoryAudio não carregado');
        return;
    }
    
    // 🏆 Tocar som de vitória MP3
    try {
        console.log('⏯️ Tentando reproduzir Vitoria.mp3...');
        victoryAudio.currentTime = 0;
        const playPromise = victoryAudio.play();
        
        if (playPromise !== undefined) {
            playPromise.then(() => {
                console.log('✅ Som de vitória iniciado com sucesso');
            }).catch(error => {
                console.log('❌ Erro ao reproduzir vitória MP3:', error);
            });
        }
    } catch (error) {
        console.log('💥 Erro no áudio de vitória:', error);
    }
}

function playFallbackWinSound() {
    if (!audioContext) return;
    
    // 🏆 FANFARRA ÉPICA DE VITÓRIA (Show do Milhão style)
    const fanfareNotes = [
        {freq: 523.25, time: 0, duration: 0.3},     // Dó
        {freq: 659.25, time: 0.1, duration: 0.3},   // Mi
        {freq: 783.99, time: 0.2, duration: 0.4},   // Sol
        {freq: 1046.50, time: 0.3, duration: 0.5},  // Dó agudo
        {freq: 1318.51, time: 0.4, duration: 0.6}   // Mi agudo
    ];
    
    fanfareNotes.forEach(note => {
        setTimeout(() => {
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            const filter = audioContext.createBiquadFilter();
            
            osc.connect(filter);
            filter.connect(gain);
            gain.connect(audioContext.destination);
            
            osc.frequency.setValueAtTime(note.freq, audioContext.currentTime);
            
            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(note.freq * 2, audioContext.currentTime);
            
            gain.gain.setValueAtTime(0.15, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + note.duration);
            
            osc.type = 'triangle';
            osc.start(audioContext.currentTime);
            osc.stop(audioContext.currentTime + note.duration);
        }, note.time * 1000);
    });
    
    // Bateria de fundo para dar impacto
    setTimeout(() => {
        for (let i = 0; i < 3; i++) {
            setTimeout(() => {
                const noise = audioContext.createBufferSource();
                const gain = audioContext.createGain();
                const filter = audioContext.createBiquadFilter();
                
                const bufferSize = audioContext.sampleRate * 0.1;
                const buffer = audioContext.createBuffer(1, bufferSize, audioContext.sampleRate);
                const data = buffer.getChannelData(0);
                
                for (let j = 0; j < bufferSize; j++) {
                    data[j] = (Math.random() * 2 - 1) * 0.5;
                }
                
                noise.buffer = buffer;
                filter.type = 'bandpass';
                filter.frequency.setValueAtTime(100, audioContext.currentTime);
                
                noise.connect(filter);
                filter.connect(gain);
                gain.connect(audioContext.destination);
                
                gain.gain.setValueAtTime(0.1, audioContext.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.1);
                
                noise.start(audioContext.currentTime);
            }, i * 150);
        }
    }, 500);
}

function playConfettiSound() {
    if (!soundEnabled || !audioContext) return;
    
    // 🎊 Som de confetti com sparkles mágicos
    for (let i = 0; i < 15; i++) {
        setTimeout(() => {
            const osc = audioContext.createOscillator();
            const gain = audioContext.createGain();
            
            osc.connect(gain);
            gain.connect(audioContext.destination);
            
            // Frequências aleatórias agudas para sparkle
            const freq = 1000 + Math.random() * 2000;
            osc.frequency.setValueAtTime(freq, audioContext.currentTime);
            osc.frequency.exponentialRampToValueAtTime(freq * 1.5, audioContext.currentTime + 0.1);
            
            gain.gain.setValueAtTime(0.02, audioContext.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, audioContext.currentTime + 0.1);
            
            osc.type = 'sine';
            osc.start(audioContext.currentTime);
            osc.stop(audioContext.currentTime + 0.1);
        }, i * 30 + Math.random() * 100);
    }
}

function toggleSound() {
    soundEnabled = !soundEnabled;
    const btn = document.getElementById('soundToggle');
    btn.textContent = soundEnabled ? '🔊' : '🔇';
    btn.classList.toggle('muted', !soundEnabled);
    
    // Parar todos os áudios se desligou o som
    if (!soundEnabled) {
        spinAudios.forEach(audio => {
            if (audio && !audio.paused) {
                audio.pause();
            }
        });
        
        if (victoryAudio && !victoryAudio.paused) {
            victoryAudio.pause();
        }
    }
}

// 🔊 CONTROLE DE VOLUME
function adjustVolume(value) {
    const volume = value / 100;
    
    // Atualizar display
    document.getElementById('volumeDisplay').textContent = value + '%';
    
    // Aplicar volume em todos os áudios de giro
    spinAudios.forEach(audio => {
        if (audio) {
            audio.volume = volume;
        }
    });
    
    // Aplicar volume no áudio de vitória
    if (victoryAudio) {
        victoryAudio.volume = volume;
    }
    
    // Salvar preferência do usuário
    localStorage.setItem('audioVolume', value);
}

// Carregar volume salvo na inicialização
function loadSavedVolume() {
    const savedVolume = localStorage.getItem('audioVolume') || '70';
    document.getElementById('volumeSlider').value = savedVolume;
    adjustVolume(savedVolume);
}

// 🎬 CONTROLE DE TELA CHEIA
function openFullscreen() {
    const overlay = document.getElementById('fullscreenOverlay');
    overlay.classList.add('show');
    
    // Inicializar áudio se necessário
    if (!audioContext) {
        initAudio();
    }
    
    // Desenhar roleta na tela cheia
    drawFullscreenWheel();
    
    // Atualizar exibição do próximo colaborador
    updateNextEmployeeDisplay();
}

function openFullscreenManual() {
    // Abrir tela cheia sem iniciar o giro automaticamente
    openFullscreen();
}

function closeFullscreen() {
    const overlay = document.getElementById('fullscreenOverlay');
    const result = document.getElementById('fullscreenResult');
    const title = document.getElementById('fullscreenTitle');
    
    overlay.classList.remove('show');
    result.classList.remove('show');
    title.textContent = '🎯 ROLETA BMZ 🎯';
}

function drawFullscreenWheel() {
    const centerX = fullscreenCanvas.width / 2;
    const centerY = fullscreenCanvas.height / 2;
    const radius = 230;

    fullscreenCtx.clearRect(0, 0, fullscreenCanvas.width, fullscreenCanvas.height);

    if (participants.length === 0) return;

    const segmentAngle = (2 * Math.PI) / participants.length;

    participants.forEach((participant, index) => {
        const startAngle = index * segmentAngle + currentRotation;
        const endAngle = startAngle + segmentAngle;

        fullscreenCtx.beginPath();
        fullscreenCtx.moveTo(centerX, centerY);
        fullscreenCtx.arc(centerX, centerY, radius, startAngle, endAngle);
        fullscreenCtx.closePath();
        
        const gradient = fullscreenCtx.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
        gradient.addColorStop(0, colors[index % colors.length]);
        gradient.addColorStop(1, colors[index % colors.length] + 'cc');
        fullscreenCtx.fillStyle = gradient;
        fullscreenCtx.fill();
        
        fullscreenCtx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
        fullscreenCtx.lineWidth = 3;
        fullscreenCtx.stroke();

        const textAngle = startAngle + segmentAngle / 2;
        const textX = centerX + Math.cos(textAngle) * (radius * 0.7);
        const textY = centerY + Math.sin(textAngle) * (radius * 0.7);
        
        fullscreenCtx.save();
        fullscreenCtx.translate(textX, textY);
        fullscreenCtx.rotate(textAngle + Math.PI / 2);
        
        fullscreenCtx.fillStyle = '#e2e8f0';
        fullscreenCtx.font = 'bold 20px Segoe UI';
        fullscreenCtx.textAlign = 'center';
        fullscreenCtx.shadowColor = 'rgba(0,0,0,0.8)';
        fullscreenCtx.shadowBlur = 3;
        
        let prize = participant.prize;
        if (prize.length > 18) prize = prize.substring(0, 18) + '...';
        fullscreenCtx.fillText(prize, 0, 5);
        
        fullscreenCtx.restore();
    });

    fullscreenCtx.beginPath();
    fullscreenCtx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
    fullscreenCtx.strokeStyle = 'rgba(255, 255, 255, 0.4)';
    fullscreenCtx.lineWidth = 8;
    fullscreenCtx.stroke();
}

function showFullscreenResult(winner) {
    const result = document.getElementById('fullscreenResult');
    const photo = document.getElementById('fullscreenWinnerPhoto');
    const name = document.getElementById('fullscreenWinnerName');
    const prize = document.getElementById('fullscreenWinnerPrize');
    
    photo.src = winner.employee.photo;
    name.textContent = winner.employee.name;
    prize.textContent = `🎁 ${winner.prize}`;
    
    result.classList.add('show');
    
    // Som de vitória
    setTimeout(() => playWinSound(), 500);
}

// 🛠️ SISTEMA DE DEBUG SECRETO
function handleLogoClick() {
    logoClickCount++;
    
    // Resetar contador após 3 segundos se não chegar a 5 cliques
    if (logoClickTimer) clearTimeout(logoClickTimer);
    logoClickTimer = setTimeout(() => {
        logoClickCount = 0;
    }, 3000);
    
    if (logoClickCount >= 5) {
        logoClickCount = 0;
        if (logoClickTimer) clearTimeout(logoClickTimer);
        toggleDebugMode();
    }
}

function toggleDebugMode() {
    debugMode = !debugMode;
    
    if (debugMode) {
        showDebugPanel();
        console.log('🛠️ Modo DEBUG ativado');
    } else {
        hideDebugPanel();
        console.log('🛠️ Modo DEBUG desativado');
    }
}

function showDebugPanel() {
    // Remover painel existente se houver
    const existingPanel = document.getElementById('debugPanel');
    if (existingPanel) existingPanel.remove();
    
    const debugHTML = `
        <div id="debugPanel" class="debug-panel">
            <div class="debug-header">
                <h3>🛠️ Painel de Debug BMZ</h3>
                <button onclick="hideDebugPanel()">×</button>
            </div>
            <div class="debug-content">
                <div class="debug-section">
                    <h4>🔊 Configurações de Áudio</h4>
                    <label>Som da Roleta:</label>
                    <select id="debugSpinSound" onchange="changeSpinSound(this.value)">
                        <option value="0" ${currentSpinSound === 0 ? 'selected' : ''}>🎵 Opção 1 (11s)</option>
                        <option value="1" ${currentSpinSound === 1 ? 'selected' : ''}>🎵 Opção 2 (11s)</option>
                        <option value="2" ${currentSpinSound === 2 ? 'selected' : ''}>🎵 Opção 3 (5s)</option>
                        <option value="3" ${currentSpinSound === 3 ? 'selected' : ''}>🎵 Opção 4 (8s)</option>
                    </select>
                    <br><br>
                    <label style="display: flex; align-items: center; margin-bottom: 10px;">
                        <input type="checkbox" id="randomSoundCheck" onchange="toggleRandomSound(this.checked)" ${randomSoundEnabled ? 'checked' : ''} style="margin-right: 8px;">
                        🎲 Som Aleatório a Cada Giro
                    </label>
                    <button onclick="testSpinSound()" class="debug-btn">🎵 Testar Som</button>
                    <button onclick="testVictorySound()" class="debug-btn">🏆 Testar Vitória</button>
                </div>
                
                <div class="debug-section">
                    <h4>🔧 Discord Webhook</h4>
                    <input type="text" id="debugWebhook" placeholder="Cole a URL do webhook aqui..." value="${discordWebhookURL}">
                    <button onclick="updateDiscordWebhook()" class="debug-btn">💾 Salvar</button>
                    <button onclick="testDiscordWebhook()" class="debug-btn">📤 Testar</button>
                </div>
                
                <div class="debug-section">
                    <h4>📊 Informações do Sistema</h4>
                    <div class="debug-info">
                        <p><strong>Colaboradores selecionados:</strong> ${selectedEmployees.length}</p>
                        <p><strong>Prêmios disponíveis:</strong> ${prizes.length}</p>
                        <p><strong>Participantes gerados:</strong> ${participants.length}</p>
                        <p><strong>Histórico de prêmios:</strong> ${prizeHistory.length}</p>
                        <p><strong>Som habilitado:</strong> ${soundEnabled ? 'Sim' : 'Não'}</p>
                        <p><strong>AudioContext:</strong> ${audioContext ? 'Ativo' : 'Inativo'}</p>
                        <p><strong>Webhook configurado:</strong> ${discordWebhookURL ? 'Sim' : 'Não'}</p>
                    </div>
                </div>
                
                <div class="debug-section">
                    <h4>🧹 Ações de Debug</h4>
                    <button onclick="clearAllData()" class="debug-btn danger">🗑️ Limpar Tudo</button>
                    <button onclick="exportDebugInfo()" class="debug-btn">📋 Exportar Log</button>
                    <button onclick="simulateError()" class="debug-btn warning">⚠️ Simular Erro</button>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', debugHTML);
}

function hideDebugPanel() {
    const panel = document.getElementById('debugPanel');
    if (panel) panel.remove();
    debugMode = false;
}

function changeSpinSound(soundIndex) {
    currentSpinSound = parseInt(soundIndex);
    localStorage.setItem('selectedSpinSound', currentSpinSound);
    console.log(`🎵 Som alterado para: ${spinSoundOptions[currentSpinSound].name}`);
}

function testSpinSound() {
    console.log('🎵 Testando som da roleta...');
    playSpinSound();
    
    // Parar após 3 segundos ou duração do áudio
    const selectedOption = spinSoundOptions[currentSpinSound];
    const testDuration = Math.min(3000, (selectedOption.duration || 3) * 1000);
    
    setTimeout(() => {
        const currentAudio = spinAudios[currentSpinSound];
        if (currentAudio) {
            currentAudio.pause();
            currentAudio.currentTime = 0;
        }
    }, testDuration);
}

function testVictorySound() {
    console.log('🏆 Testando som de vitória...');
    playWinSound();
}

function toggleRandomSound(enabled) {
    randomSoundEnabled = enabled;
    localStorage.setItem('randomSoundEnabled', randomSoundEnabled);
    
    if (debugMode) {
        console.log(`🎲 Som aleatório ${enabled ? 'ativado' : 'desativado'}`);
    }
}

function updateDiscordWebhook() {
    const newWebhook = document.getElementById('debugWebhook').value.trim();
    if (newWebhook && newWebhook.includes('discord.com/api/webhooks/')) {
        discordWebhookURL = newWebhook;
        localStorage.setItem('discordWebhook', discordWebhookURL);
        updateDiscordButton();
        console.log('✅ Webhook do Discord atualizado');
        alert('✅ Webhook configurado com sucesso!');
    } else if (newWebhook === '') {
        discordWebhookURL = '';
        localStorage.removeItem('discordWebhook');
        updateDiscordButton();
        console.log('🗑️ Webhook removido');
        alert('🗑️ Webhook removido');
    } else {
        console.error('❌ URL de webhook inválida');
        alert('❌ URL inválida! Deve ser um webhook do Discord.');
    }
}

function testDiscordWebhook() {
    if (!discordWebhookURL) {
        alert('❌ Configure um webhook primeiro!');
        return;
    }
    
    console.log('📤 Testando webhook do Discord...');
    
    const testPayload = {
        username: "Teste BMZ Debug 🛠️",
        avatar_url: "https://cdn-icons-png.flaticon.com/512/2006/2006249.png",
        embeds: [{
            title: "🧪 Teste de Webhook",
            description: "Este é um teste do sistema de debug da Roleta BMZ.",
            color: 0x00ff00,
            fields: [{
                name: "Status",
                value: "✅ Webhook funcionando corretamente!",
                inline: false
            }],
            footer: {
                text: "Sistema de Debug BMZ",
                icon_url: "https://cdn-icons-png.flaticon.com/512/2995/2995463.png"
            },
            timestamp: new Date().toISOString()
        }]
    };
    
    fetch(discordWebhookURL, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(testPayload)
    })
    .then(response => {
        if (response.ok) {
            console.log('✅ Teste de webhook bem-sucedido');
            alert('✅ Teste enviado com sucesso! Verifique o Discord.');
        } else {
            console.error('❌ Erro no teste de webhook:', response.status);
            alert(`❌ Erro ${response.status}: Verifique o webhook.`);
        }
    })
    .catch(error => {
        console.error('🔌 Erro de conexão:', error);
        alert('🔌 Falha na conexão com o Discord.');
    });
}

function clearAllData() {
    if (confirm('⚠️ Isso irá limpar TODOS os dados (colaboradores, prêmios, histórico). Continuar?')) {
        selectedEmployees = [];
        prizes = [];
        participants = [];
        prizeHistory = [];
        currentRotation = 0;
        
        // Limpar interface
        document.querySelectorAll('.employee-card').forEach(card => {
            card.classList.remove('selected');
        });
        
        updatePrizesList();
        updateParticipants();
        updatePrizeHistoryDisplay();
        updateDisplay();
        
        console.log('🧹 Todos os dados foram limpos');
        alert('🧹 Dados limpos com sucesso!');
    }
}

function exportDebugInfo() {
    const debugInfo = {
        timestamp: new Date().toISOString(),
        system: {
            selectedEmployees: selectedEmployees.length,
            prizes: prizes.length,
            participants: participants.length,
            prizeHistory: prizeHistory.length,
            soundEnabled: soundEnabled,
            currentSpinSound: spinSoundOptions[currentSpinSound].name,
            webhookConfigured: !!discordWebhookURL,
            debugMode: debugMode
        },
        data: {
            selectedEmployees: selectedEmployees,
            prizes: prizes,
            prizeHistory: prizeHistory
        },
        errors: JSON.parse(localStorage.getItem('bmzErrors') || '[]')
    };
    
    const blob = new Blob([JSON.stringify(debugInfo, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `bmz-debug-${new Date().toISOString().slice(0, 19).replace(/:/g, '-')}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    
    console.log('📋 Informações de debug exportadas');
}

function simulateError() {
    const errorMsg = `Erro simulado em ${new Date().toISOString()}`;
    console.error('⚠️ ' + errorMsg);
    logError('SIMULATED', errorMsg);
    alert('⚠️ Erro simulado registrado no console e no log.');
}

function logError(type, message, details = null) {
    const error = {
        timestamp: new Date().toISOString(),
        type: type,
        message: message,
        details: details,
        url: window.location.href,
        userAgent: navigator.userAgent
    };
    
    const errors = JSON.parse(localStorage.getItem('bmzErrors') || '[]');
    errors.push(error);
    
    // Manter apenas os últimos 50 erros
    if (errors.length > 50) {
        errors.splice(0, errors.length - 50);
    }
    
    localStorage.setItem('bmzErrors', JSON.stringify(errors));
    
    if (debugMode) {
        console.error('🐛 Erro registrado:', error);
    }
}

// 🚀 Inicialização
function init() {
    loadEmployees();
    updatePrizesList();
    updateDisplay();
    updateDiscordButton(); // Verificar se webhook já está configurado
    loadSavedVolume(); // Carregar volume salvo
    
    // Carregar configuração de som salva
    const savedSpinSound = localStorage.getItem('selectedSpinSound');
    if (savedSpinSound !== null) {
        currentSpinSound = parseInt(savedSpinSound);
    }
    
    // Carregar ordem dos colaboradores salva
    const savedOrder = localStorage.getItem('employeeOrder');
    if (savedOrder) {
        employeeOrder = savedOrder;
    }
    
    // Carregar configuração de som aleatório
    const savedRandomSound = localStorage.getItem('randomSoundEnabled');
    if (savedRandomSound !== null) {
        randomSoundEnabled = savedRandomSound === 'true';
    }
    
    // Sincronizar seletor da interface
    const orderSelector = document.getElementById('employeeOrderSelect');
    
    if (orderSelector) {
        orderSelector.value = employeeOrder;
    }
    
    // Adicionar evento de clique na logo
    const logo = document.querySelector('.logo-placeholder');
    if (logo) {
        logo.addEventListener('click', handleLogoClick);
        logo.style.cursor = 'pointer';
    }
    
    // Adicionar eventos de arrasto com mouse
    addMouseDragEvents();
    
    console.log('🎯 Roleta BMZ inicializada com todas as funcionalidades');
}

// 📋 Gerenciamento de abas
function switchTab(tabName) {
    document.querySelectorAll('.tab').forEach(tab => tab.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tabName + '-tab').classList.add('active');
}

// 👥 Carregamento de colaboradores
function loadEmployees() {
    const grid = document.getElementById('employeeGrid');
    grid.innerHTML = '';
    
    employees.forEach((employee, index) => {
        const card = document.createElement('div');
        card.className = 'employee-card';
        card.onclick = () => toggleEmployee(index);
        
        card.innerHTML = `
            <img src="${employee.photo}" alt="${employee.name}" class="employee-photo">
            <div class="employee-name">${employee.name}</div>
        `;
        
        grid.appendChild(card);
    });
}

// ✅ Seleção de colaboradores (APENAS UM POR VEZ)
function toggleEmployee(index) {
    const card = document.querySelectorAll('.employee-card')[index];
    
    if (selectedEmployees.includes(index)) {
        // Desselecionar o colaborador atual
        selectedEmployees = [];
        card.classList.remove('selected');
    } else {
        // Desselecionar todos os outros primeiro
        document.querySelectorAll('.employee-card').forEach(c => c.classList.remove('selected'));
        selectedEmployees = [index];
        card.classList.add('selected');
    }
    
    updateParticipants();
}

// 🎲 Seleção automática do próximo colaborador baseada na ordem escolhida
function selectNextEmployee() {
    if (employees.length === 0) return;
    
    let sortedEmployees = [...employees];
    
    // Ordenar baseado na configuração
    switch(employeeOrder) {
        case 'alphabetic':
            sortedEmployees.sort((a, b) => a.name.localeCompare(b.name));
            break;
        case 'reverse':
            sortedEmployees.sort((a, b) => b.name.localeCompare(a.name));
            break;
        case 'random':
        default:
            // Para ordem aleatória, remover quem já ganhou até todos ganharem
            const allWinners = prizeHistory.map(entry => entry.name);
            const availableEmployees = sortedEmployees.filter(emp => !allWinners.includes(emp.name));
            
            // Se todos já ganharam, resetar e permitir todos novamente
            if (availableEmployees.length === 0) {
                console.log('🔄 Todos colaboradores já ganharam - resetando lista');
                sortedEmployees = [...employees];
            } else {
                sortedEmployees = availableEmployees;
            }
            break;
    }
    
    let selectedEmployee;
    
    if (employeeOrder === 'random') {
        selectedEmployee = sortedEmployees[Math.floor(Math.random() * sortedEmployees.length)];
    } else {
        // Para ordem alfabética, pegar o próximo após o atual ou o primeiro
        const currentIndex = selectedEmployees.length > 0 ? selectedEmployees[0] : -1;
        const currentName = currentIndex !== -1 ? employees[currentIndex].name : '';
        const currentSortedIndex = sortedEmployees.findIndex(emp => emp.name === currentName);
        const nextIndex = currentSortedIndex !== -1 ? (currentSortedIndex + 1) % sortedEmployees.length : 0;
        selectedEmployee = sortedEmployees[nextIndex];
    }
    
    const employeeIndex = employees.findIndex(emp => emp.name === selectedEmployee.name);
    
    if (employeeIndex !== -1) {
        // Desselecionar atual
        document.querySelectorAll('.employee-card').forEach(c => c.classList.remove('selected'));
        
        // Selecionar o novo
        selectedEmployees = [employeeIndex];
        document.querySelectorAll('.employee-card')[employeeIndex].classList.add('selected');
        
        updateParticipants();
        nextEmployeeName = selectedEmployee.name;
        updateNextEmployeeDisplay();
        
        if (debugMode) {
            console.log(`🎲 Próximo colaborador selecionado (${employeeOrder}): ${selectedEmployee.name}`);
        }
        
        return selectedEmployee.name;
    }
}

// Função compatível com o antigo nome
function selectRandomEmployee() {
    return selectNextEmployee();
}

// 📋 Atualizar exibição do próximo colaborador
function updateNextEmployeeDisplay() {
    const display = document.getElementById('nextEmployeeDisplay');
    if (display && nextEmployeeName && selectedEmployees.length > 0) {
        display.textContent = `👤 Próximo a Rodar: ${nextEmployeeName}`;
        display.style.display = 'block';
    } else if (display) {
        display.style.display = 'none';
    }
}

// 🔄 Alterar ordem dos colaboradores
function changeEmployeeOrder(order) {
    employeeOrder = order;
    localStorage.setItem('employeeOrder', employeeOrder);
    
    if (debugMode) {
        console.log(`📋 Ordem dos colaboradores alterada para: ${employeeOrder}`);
    }
    
    // Se tem colaborador selecionado, mostrar o próximo baseado na nova ordem
    if (selectedEmployees.length > 0 && prizes.length > 0) {
        const currentName = employees[selectedEmployees[0]].name;
        nextEmployeeName = getNextEmployeeName(currentName);
        updateNextEmployeeDisplay();
    }
}

// 📋 Obter próximo colaborador baseado na ordem
function getNextEmployeeName(currentName) {
    if (employees.length === 0) return '';
    
    let sortedEmployees = [...employees];
    
    switch(employeeOrder) {
        case 'alphabetic':
            sortedEmployees.sort((a, b) => a.name.localeCompare(b.name));
            break;
        case 'reverse':
            sortedEmployees.sort((a, b) => b.name.localeCompare(a.name));
            break;
        case 'random':
        default:
            // Para ordem aleatória, remover quem já ganhou
            const allWinners = prizeHistory.map(entry => entry.name);
            const availableEmployees = sortedEmployees.filter(emp => !allWinners.includes(emp.name));
            
            if (availableEmployees.length === 0) {
                // Se todos ganharam, usar todos
                return sortedEmployees[Math.floor(Math.random() * sortedEmployees.length)].name;
            } else {
                return availableEmployees[Math.floor(Math.random() * availableEmployees.length)].name;
            }
    }
    
    const currentIndex = sortedEmployees.findIndex(emp => emp.name === currentName);
    const nextIndex = currentIndex !== -1 ? (currentIndex + 1) % sortedEmployees.length : 0;
    return sortedEmployees[nextIndex].name;
}

// 🎁 Gerenciamento de prêmios
function addPrize() {
    const input = document.getElementById('prizeInput');
    const prize = input.value.trim();
    
    if (prize && !prizes.includes(prize)) {
        prizes.push(prize);
        input.value = '';
        updatePrizesList();
        updateParticipants();
        
        input.style.transform = 'scale(1.05)';
        setTimeout(() => input.style.transform = 'scale(1)', 200);
    } else if (prizes.includes(prize)) {
        alert('Este prêmio já foi adicionado!');
    }
}

function removePrize(index) {
    prizes.splice(index, 1);
    updatePrizesList();
    updateParticipants();
}

function clearPrizes() {
    if (prizes.length > 0 && confirm('Limpar todos os prêmios?')) {
        prizes = [];
        updatePrizesList();
        updateParticipants();
    }
}

function importJSON() {
    document.getElementById('jsonFileInput').click();
}

// Função para processar o arquivo JSON selecionado
function handleFileImport(event) {
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = function(e) {
        try {
            const jsonData = JSON.parse(e.target.result);
            
            // Verificar se é um array de strings
            if (Array.isArray(jsonData)) {
                let importedCount = 0;
                
                jsonData.forEach(item => {
                    // Converter para string se não for
                    const prizeText = typeof item === 'string' ? item : String(item);
                    
                    // Verificar se o prêmio já existe
                    if (prizeText.trim() && !prizes.includes(prizeText.trim())) {
                        prizes.push(prizeText.trim());
                        importedCount++;
                    }
                });
                
                if (importedCount > 0) {
                    updatePrizesList();
                    updateParticipants();
                    alert(`✅ ${importedCount} prêmio(s) importado(s) com sucesso!`);
                } else {
                    alert('ℹ️ Nenhum prêmio novo foi encontrado no arquivo.');
                }
            } else {
                // Se for um objeto, tentar extrair uma propriedade que contenha array
                const possibleArrays = Object.values(jsonData).filter(value => Array.isArray(value));
                
                if (possibleArrays.length > 0) {
                    const firstArray = possibleArrays[0];
                    let importedCount = 0;
                    
                    firstArray.forEach(item => {
                        const prizeText = typeof item === 'string' ? item : String(item);
                        
                        if (prizeText.trim() && !prizes.includes(prizeText.trim())) {
                            prizes.push(prizeText.trim());
                            importedCount++;
                        }
                    });
                    
                    if (importedCount > 0) {
                        updatePrizesList();
                        updateParticipants();
                        alert(`✅ ${importedCount} prêmio(s) importado(s) com sucesso!`);
                    } else {
                        alert('ℹ️ Nenhum prêmio novo foi encontrado no arquivo.');
                    }
                } else {
                    alert('❌ Formato JSON inválido. O arquivo deve conter um array de prêmios.');
                }
            }
            
        } catch (error) {
            alert('❌ Erro ao ler o arquivo JSON. Verifique se o formato está correto.');
            console.error('Erro ao processar JSON:', error);
        }
        
        // Limpar o input para permitir reimportar o mesmo arquivo
        event.target.value = '';
    };
    
    reader.readAsText(file);
}

function updatePrizesList() {
    const list = document.getElementById('prizesList');
    
    if (prizes.length === 0) {
        list.innerHTML = '<div class="empty-state">Nenhum prêmio adicionado</div>';
        return;
    }
    
    list.innerHTML = prizes.map((prize, index) => `
        <div class="prize-item">
            <span>🎁 ${prize}</span>
            <button class="remove-btn" onclick="removePrize(${index})">×</button>
        </div>
    `).join('');
}

// 🎯 Criação de participantes
function updateParticipants() {
    participants = [];
    
    selectedEmployees.forEach(empIndex => {
        prizes.forEach(prize => {
            participants.push({
                employee: employees[empIndex],
                prize: prize
            });
        });
    });
    
    // Atualizar próximo colaborador se há prêmios
    if (selectedEmployees.length > 0 && prizes.length > 0) {
        const currentName = employees[selectedEmployees[0]].name;
        nextEmployeeName = getNextEmployeeName(currentName);
        updateNextEmployeeDisplay();
    } else {
        nextEmployeeName = '';
        updateNextEmployeeDisplay();
    }
    
    updateDisplay();
}

function removeParticipant(index) {
    const participant = participants[index];
    const empIndex = employees.findIndex(emp => emp.name === participant.employee.name);
    
    const sameEmployeeCount = participants.filter(p => p.employee.name === participant.employee.name).length;
    if (sameEmployeeCount === 1) {
        selectedEmployees = selectedEmployees.filter(i => i !== empIndex);
        document.querySelectorAll('.employee-card')[empIndex].classList.remove('selected');
    }
    
    updateParticipants();
}

// 🎨 Atualização visual
function updateDisplay() {
    drawWheel();
    updatePrizeHistoryDisplay();
    updateSpinButton();
}

function drawWheel() {
    const centerX = canvas.width / 2;
    const centerY = canvas.height / 2;
    const radius = 160;

    ctx.clearRect(0, 0, canvas.width, canvas.height);

    if (participants.length === 0) {
        ctx.beginPath();
        ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
        const gradient = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
        gradient.addColorStop(0, '#2d3748');
        gradient.addColorStop(1, '#1a202c');
        ctx.fillStyle = gradient;
        ctx.fill();
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.1)';
        ctx.lineWidth = 4;
        ctx.stroke();
        
        ctx.fillStyle = '#718096';
        ctx.font = 'bold 16px Segoe UI';
        ctx.textAlign = 'center';
        ctx.fillText('Selecione colaboradores', centerX, centerY - 10);
        ctx.fillText('e adicione prêmios!', centerX, centerY + 15);
        return;
    }

    const segmentAngle = (2 * Math.PI) / participants.length;

    participants.forEach((participant, index) => {
        const startAngle = index * segmentAngle + currentRotation;
        const endAngle = startAngle + segmentAngle;

        ctx.beginPath();
        ctx.moveTo(centerX, centerY);
        ctx.arc(centerX, centerY, radius, startAngle, endAngle);
        ctx.closePath();
        
        const gradient = ctx.createRadialGradient(centerX, centerY, 0, centerX, centerY, radius);
        gradient.addColorStop(0, colors[index % colors.length]);
        gradient.addColorStop(1, colors[index % colors.length] + 'cc');
        ctx.fillStyle = gradient;
        ctx.fill();
        
        ctx.strokeStyle = 'rgba(255, 255, 255, 0.2)';
        ctx.lineWidth = 2;
        ctx.stroke();

        const textAngle = startAngle + segmentAngle / 2;
        const textX = centerX + Math.cos(textAngle) * (radius * 0.7);
        const textY = centerY + Math.sin(textAngle) * (radius * 0.7);
        
        ctx.save();
        ctx.translate(textX, textY);
        ctx.rotate(textAngle + Math.PI / 2);
        
        ctx.fillStyle = '#e2e8f0';
        ctx.font = 'bold 13px Segoe UI';
        ctx.textAlign = 'center';
        ctx.shadowColor = 'rgba(0,0,0,0.8)';
        ctx.shadowBlur = 2;
        
        let prize = participant.prize;
        if (prize.length > 15) prize = prize.substring(0, 15) + '...';
        ctx.fillText(prize, 0, 2);
        
        ctx.restore();
    });

    ctx.beginPath();
    ctx.arc(centerX, centerY, radius, 0, 2 * Math.PI);
    ctx.strokeStyle = 'rgba(255, 255, 255, 0.3)';
    ctx.lineWidth = 6;
    ctx.stroke();
}

function updateParticipantsList() {
    const list = document.getElementById('participantsList');
    const count = document.getElementById('participantCount');
    
    count.textContent = participants.length;
    
    if (participants.length === 0) {
        list.innerHTML = '<div class="empty-state">Selecione colaboradores e adicione prêmios para começar!</div>';
        return;
    }
    
    list.innerHTML = participants.map((participant, index) => `
        <div class="participant-item">
            <div class="participant-info">
                <img src="${participant.employee.photo}" class="participant-photo" alt="${participant.employee.name}">
                <div class="participant-details">
                    <div class="participant-name">${participant.employee.name}</div>
                    <div class="participant-prize">🎁 ${participant.prize}</div>
                </div>
            </div>
            <button class="remove-btn" onclick="removeParticipant(${index})">×</button>
        </div>
    `).join('');
}

// 🔧 CORREÇÃO: Agora verifica PRÊMIOS ao invés de participantes
function updateSpinButton() {
    const btn = document.getElementById('spinBtn');
    const fullscreenBtn = document.getElementById('fullscreenSpinBtn');
    const hasEmployees = selectedEmployees.length > 0;
    const hasPrizes = prizes.length >= 2; // 🎯 CORRIGIDO: Pelo menos 2 prêmios
    
    const canSpin = hasEmployees && hasPrizes && !isSpinning;
    
    // Botão principal
    btn.disabled = !canSpin;
    
    if (!hasEmployees) {
        btn.textContent = '🎯 SELECIONE PELO MENOS 1 COLABORADOR';
    } else if (!hasPrizes) {
        btn.textContent = '🎯 ADICIONE PELO MENOS 2 PRÊMIOS'; // 🎯 CORRIGIDO
    } else if (isSpinning) {
        btn.textContent = '🌀 GIRANDO...';
    } else {
        btn.textContent = '🎯 GIRAR ROLETA';
    }
    
    // Botão da tela cheia
    if (fullscreenBtn) {
        fullscreenBtn.disabled = !canSpin;
        if (isSpinning) {
            fullscreenBtn.textContent = '🌀 Girando...';
        } else {
            fullscreenBtn.textContent = '🎯 Girar';
        }
    }
}

// 🎪 Animação da roleta (10 SEGUNDOS)
function spinWheel() {
    const hasEmployees = selectedEmployees.length > 0;
    const hasPrizes = prizes.length >= 2;
    
    if (!hasEmployees || !hasPrizes || isSpinning) return;
    
    isSpinning = true;
    hideResult();
    updateSpinButton();
    
    // Parar qualquer momentum em andamento
    if (momentumTimer) {
        clearInterval(momentumTimer);
        momentumTimer = null;
        if (debugMode) {
            console.log('🛑 Momentum interrompido por giro manual');
        }
    }
    
    // Abrir tela cheia se não estiver aberta
    const overlay = document.getElementById('fullscreenOverlay');
    if (!overlay.classList.contains('show')) {
        openFullscreen();
    }
    
    // Adicionar classe de animação
    overlay.classList.add('spinning');
    
    // 🎵 Tocar som (pode ser aleatório)
    const usedSoundIndex = playSpinSound();
    
    const minSpins = 8;  // Mais voltas para duração variável
    const maxSpins = 12;
    const spins = minSpins + Math.random() * (maxSpins - minSpins);
    const finalAngle = Math.random() * 2 * Math.PI;
    const totalRotation = spins * 2 * Math.PI + finalAngle;
    
    // 🎯 Usar duração do áudio que está realmente tocando
    const actualSoundIndex = usedSoundIndex !== undefined ? usedSoundIndex : currentSpinSound;
    const selectedOption = spinSoundOptions[actualSoundIndex];
    const duration = (selectedOption.duration || 10) * 1000;
    const startTime = Date.now();
    const startRotation = currentRotation;
    
    // Atualizar título durante o giro
    const title = document.getElementById('fullscreenTitle');
    title.textContent = '🌪️ RODANDO 🌪️';
    
    function animate() {
        const elapsed = Date.now() - startTime;
        const progress = Math.min(elapsed / duration, 1);
        
        // 🎯 Curva de animação mais suave para 10 segundos
        const easeProgress = 1 - Math.pow(1 - progress, 2.5); // Curva mais suave
        
        currentRotation = startRotation + totalRotation * easeProgress;
        drawWheel();
        drawFullscreenWheel();
        
        if (progress < 1) {
            requestAnimationFrame(animate);
        } else {
            isSpinning = false;
            updateSpinButton();
            
            // 🎵 Parar o áudio caso ainda esteja tocando
            const currentAudio = spinAudios[actualSoundIndex];
            if (currentAudio) {
                currentAudio.pause();
                currentAudio.currentTime = 0;
            }
            
            // Remover classe de animação
            overlay.classList.remove('spinning');
            
            // Calcular vencedor
            const segmentAngle = (2 * Math.PI) / participants.length;
            const pointerPosition = -Math.PI / 2;
            const currentAngleAtPointer = pointerPosition - currentRotation;
            const normalizedAngle = ((currentAngleAtPointer % (2 * Math.PI)) + (2 * Math.PI)) % (2 * Math.PI);
            const winnerIndex = Math.floor(normalizedAngle / segmentAngle);
            const winner = participants[winnerIndex];
            
            console.log('🏆 Vencedor:', winner);
            
            // Atualizar título
            title.textContent = '🎯 E O VENCEDOR É... 🎯';
            
            // Mostrar resultado após um delay dramático
            setTimeout(() => {
                showResult(winner);
                showFullscreenResult(winner);
                createFireworks();
            }, 1500);
        }
    }
    
    animate();
}

// 🏆 Resultado
function showResult(winner) {
    const result = document.getElementById('result');
    const photo = document.getElementById('winnerPhoto');
    const name = document.getElementById('winnerName');
    const prize = document.getElementById('winnerPrize');
    
    photo.src = winner.employee.photo;
    photo.classList.add('show');
    name.textContent = winner.employee.name;
    prize.textContent = `🎁 ${winner.prize}`;
    
    // Adicionar ao histórico
    prizeHistory.push({
        name: winner.employee.name,
        photo: winner.employee.photo,
        prize: winner.prize
    });
    
    // 🎯 Remover o prêmio da lista automaticamente
    const prizeIndex = prizes.indexOf(winner.prize);
    if (prizeIndex !== -1) {
        prizes.splice(prizeIndex, 1);
        console.log(`🎁 Prêmio "${winner.prize}" removido da lista`);
        
        // Atualizar as listas e participantes
        updatePrizesList();
        updateParticipants();
    }
    
    updatePrizeHistoryDisplay();
    result.classList.add('show');
    createConfetti();
    
    // 🎲 Selecionar automaticamente o próximo colaborador após 3 segundos
    if (prizes.length > 0) {
        setTimeout(() => {
            const nextEmployee = selectNextEmployee();
            if (nextEmployee && debugMode) {
                console.log(`🎲 Próximo colaborador selecionado: ${nextEmployee}`);
            }
        }, 3000);
    }
}

function hideResult() {
    const result = document.getElementById('result');
    const photo = document.getElementById('winnerPhoto');
    const fullscreenResult = document.getElementById('fullscreenResult');
    
    result.classList.remove('show');
    photo.classList.remove('show');
    
    if (fullscreenResult) {
        fullscreenResult.classList.remove('show');
    }
}

// 🎊 Confetti elegante
function createConfetti() {
    const confettiColors = ['#4a5568', '#2d3748', '#e2e8f0', '#cbd5e0'];
    
    for (let i = 0; i < 30; i++) {
        setTimeout(() => {
            const confetti = document.createElement('div');
            confetti.style.cssText = `
                position: fixed;
                left: ${Math.random() * 100}vw;
                top: -10px;
                width: 8px;
                height: 8px;
                background: ${confettiColors[Math.floor(Math.random() * confettiColors.length)]};
                pointer-events: none;
                border-radius: 50%;
                animation: confettiFall ${2 + Math.random() * 3}s linear forwards;
                z-index: 1000;
            `;
            
            document.body.appendChild(confetti);
            setTimeout(() => confetti.remove(), 5000);
        }, i * 100);
    }
}

// 🎆 FOGOS DE ARTIFÍCIO ESPETACULARES
function createFireworks() {
    const fireworkColors = ['#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7', '#fd79a8', '#a29bfe'];
    
    for (let burst = 0; burst < 5; burst++) {
        setTimeout(() => {
            const centerX = Math.random() * window.innerWidth;
            const centerY = Math.random() * window.innerHeight * 0.7;
            
            for (let i = 0; i < 20; i++) {
                const angle = (Math.PI * 2 * i) / 20;
                const velocity = 100 + Math.random() * 150;
                
                const firework = document.createElement('div');
                firework.style.cssText = `
                    position: fixed;
                    left: ${centerX}px;
                    top: ${centerY}px;
                    width: 8px;
                    height: 8px;
                    background: ${fireworkColors[Math.floor(Math.random() * fireworkColors.length)]};
                    border-radius: 50%;
                    pointer-events: none;
                    box-shadow: 0 0 20px currentColor;
                    z-index: 1001;
                `;
                
                const dx = Math.cos(angle) * velocity;
                const dy = Math.sin(angle) * velocity;
                
                firework.animate([
                    { transform: 'translate(0, 0) scale(1)', opacity: 1 },
                    { transform: `translate(${dx}px, ${dy}px) scale(0)`, opacity: 0 }
                ], {
                    duration: 1000 + Math.random() * 500,
                    easing: 'ease-out'
                }).onfinish = () => firework.remove();
                
                document.body.appendChild(firework);
            }
        }, burst * 200);
    }
}

// Event listeners
document.getElementById('prizeInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') addPrize();
});

function updatePrizeHistoryDisplay() {
    const list = document.getElementById('prizeHistoryList');
    if (prizeHistory.length === 0) {
        list.innerHTML = '<div class="empty-state">Nenhum prêmio foi sorteado ainda.</div>';
        return;
    }

    list.innerHTML = prizeHistory.map(entry => `
        <div class="participant-item">
            <div class="participant-info">
                <img src="${entry.photo}" class="participant-photo" alt="${entry.name}">
                <div class="participant-details">
                    <div class="participant-name">${entry.name}</div>
                    <div class="participant-prize">🎁 ${entry.prize}</div>
                </div>
            </div>
        </div>
    `).join('');
}

// 📥 FUNÇÃO PARA BAIXAR RELATÓRIO TXT
function downloadReport() {
    if (prizeHistory.length === 0) {
        alert("📋 Nenhum prêmio para exportar no relatório.");
        return;
    }

    const currentDate = new Date();
    const dateString = currentDate.toLocaleDateString('pt-BR', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    const timeString = currentDate.toLocaleTimeString('pt-BR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });

    let reportContent = '';
    reportContent += '═══════════════════════════════════════════════════════════\n';
    reportContent += '🏆               RELATÓRIO OFICIAL BMZ                🏆\n';
    reportContent += '                   SORTEIO DE PRÊMIOS                     \n';
    reportContent += '═══════════════════════════════════════════════════════════\n\n';
    
    reportContent += `📅 DATA: ${dateString}\n`;
    reportContent += `🕐 HORÁRIO: ${timeString}\n`;
    reportContent += `🎯 TOTAL DE PRÊMIOS SORTEADOS: ${prizeHistory.length}\n\n`;
    
    reportContent += '───────────────────────────────────────────────────────────\n';
    reportContent += '🎁                    RESULTADOS                     🎁\n';
    reportContent += '───────────────────────────────────────────────────────────\n\n';
    
    prizeHistory.forEach((entry, index) => {
        reportContent += `${(index + 1).toString().padStart(2, '0')}º LUGAR\n`;
        reportContent += `👤 COLABORADOR: ${entry.name}\n`;
        reportContent += `🎁 PRÊMIO: ${entry.prize}\n\n`;
    });
    
    // Estatísticas
    const uniqueWinners = [...new Set(prizeHistory.map(entry => entry.name))];
    reportContent += '───────────────────────────────────────────────────────────\n';
    reportContent += '📊                  ESTATÍSTICAS                    📊\n';
    reportContent += '───────────────────────────────────────────────────────────\n\n';
    reportContent += `👥 PESSOAS CONTEMPLADAS: ${uniqueWinners.length}\n`;
    reportContent += `🎁 TOTAL DE PRÊMIOS: ${prizeHistory.length}\n`;
    reportContent += `✅ SORTEIO REALIZADO COM SUCESSO!\n\n`;
    
    reportContent += '═══════════════════════════════════════════════════════════\n';
    reportContent += '              Gerado pelo Sistema BMZ                     \n';
    reportContent += '               🎯 ROLETA DE SORTEIOS 🎯                   \n';
    reportContent += '═══════════════════════════════════════════════════════════\n';

    // Criar e baixar arquivo
    const blob = new Blob([reportContent], { type: 'text/plain;charset=utf-8' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    
    const fileName = `BMZ_Sorteio_${currentDate.getFullYear()}-${(currentDate.getMonth() + 1).toString().padStart(2, '0')}-${currentDate.getDate().toString().padStart(2, '0')}_${currentDate.getHours().toString().padStart(2, '0')}h${currentDate.getMinutes().toString().padStart(2, '0')}.txt`;
    
    link.download = fileName;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    alert(`📄 Relatório baixado: ${fileName}`);
}

// 🔄 FUNÇÃO SEGURA PARA DISCORD (COM TRATAMENTO DE ERROS)
function exportarParaDiscord() {
    try {
        if (!discordWebhookURL) {
            configureDiscordWebhook();
            return;
        }
        
        if (prizeHistory.length === 0) {
            alert("📋 Nenhum prêmio para exportar.");
            return;
        }

        const currentDate = new Date();
        const dateString = currentDate.toLocaleDateString('pt-BR', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
        const timeString = currentDate.toLocaleTimeString('pt-BR', { 
            hour: '2-digit', 
            minute: '2-digit' 
        });

        // 🎯 Formatação rica para Discord
        const embed = {
            title: "🏆 Sorteio BMZ - Resultados Oficiais",
            description: `📅 **Data:** ${dateString}\n🕐 **Horário:** ${timeString}\n\n🎯 **Total de prêmios sorteados:** ${prizeHistory.length}`,
            color: 0x4a5568,
            thumbnail: {
                url: "https://cdn-icons-png.flaticon.com/512/9028/9028011.png"
            },
            fields: [],
            footer: {
                text: "Sistema de Sorteios BMZ • Operacional",
                icon_url: "https://cdn-icons-png.flaticon.com/512/2995/2995463.png"
            },
            timestamp: new Date().toISOString()
        };

        // Adicionar cada vencedor como um field
        prizeHistory.forEach((entry, index) => {
            embed.fields.push({
                name: `${index + 1}º Lugar 🥇`,
                value: `👤 **${entry.name}**\n🎁 *${entry.prize}*`,
                inline: true
            });
        });

        // Se houver muitos prêmios, fazer uma versão condensada
        if (prizeHistory.length > 10) {
            embed.fields = [];
            
            let winners = "";
            prizeHistory.forEach((entry, index) => {
                winners += `**${index + 1}.** ${entry.name} → *${entry.prize}*\n`;
            });

            embed.fields.push({
                name: "🎊 Lista Completa de Vencedores",
                value: winners,
                inline: false
            });
        }

        // Adicionar estatísticas
        const uniqueWinners = [...new Set(prizeHistory.map(entry => entry.name))];
        embed.fields.push({
            name: "📊 Estatísticas",
            value: `👥 **Pessoas contempladas:** ${uniqueWinners.length}\n🎁 **Total de prêmios:** ${prizeHistory.length}\n🎯 **Sorteio realizado com sucesso!**`,
            inline: false
        });

        const payload = {
            username: "Roleta BMZ 🎯",
            avatar_url: "https://cdn-icons-png.flaticon.com/512/2006/2006249.png",
            embeds: [embed]
        };

        if (debugMode) {
            console.log('📤 Enviando para Discord:', payload);
        }

        fetch(discordWebhookURL, {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
        })
        .then(response => {
            if (response.ok) {
                if (debugMode) {
                    console.log('✅ Exportação para Discord bem-sucedida');
                }
                alert("✅ Histórico enviado para o Discord com sucesso!\n🎊 Confira o canal para ver os resultados formatados.");
            } else {
                const errorMsg = `Erro HTTP ${response.status}: ${response.statusText}`;
                logError('DISCORD_EXPORT', errorMsg, {
                    status: response.status,
                    statusText: response.statusText,
                    url: discordWebhookURL.substring(0, 50) + '...'
                });
                
                if (debugMode) {
                    console.error('❌ Erro na exportação:', errorMsg);
                }
                
                alert(`❌ Erro ao enviar para o Discord (${response.status}).\nVerifique o webhook no painel de debug.`);
            }
        })
        .catch(error => {
            logError('DISCORD_NETWORK', 'Falha na conexão com Discord', {
                error: error.message,
                stack: error.stack
            });
            
            if (debugMode) {
                console.error("🔌 Erro de conexão:", error);
            }
            
            alert("🔌 Falha na conexão com o Discord.\nVerifique sua internet e tente novamente.");
        });
        
    } catch (error) {
        logError('DISCORD_UNEXPECTED', 'Erro inesperado na exportação', {
            error: error.message,
            stack: error.stack
        });
        
        if (debugMode) {
            console.error('💥 Erro inesperado:', error);
        }
        
        alert('💥 Erro inesperado na exportação.\nConsulte o painel de debug para mais detalhes.');
    }
}

// 🖱️ FUNCIONALIDADE DE GIRAR COM MOUSE (ARRASTAR)
let isDragging = false;
let startAngle = 0;
let dragStartAngle = 0;
let dragSensitivity = 0.01;
let dragVelocity = 0;
let lastDragTime = 0;
let lastDragAngle = 0;
let dragHistory = [];
let momentumTimer = null;

function addMouseDragEvents() {
    const canvas = document.getElementById('wheelCanvas');
    const fullscreenCanvas = document.getElementById('fullscreenCanvas');
    
    [canvas, fullscreenCanvas].forEach(canvasElement => {
        if (!canvasElement) return;
        
        // Mouse events
        canvasElement.addEventListener('mousedown', handleDragStart);
        canvasElement.addEventListener('mousemove', handleDragMove);
        canvasElement.addEventListener('mouseup', handleDragEnd);
        canvasElement.addEventListener('mouseleave', handleDragEnd);
        
        // Touch events for mobile
        canvasElement.addEventListener('touchstart', handleTouchStart, { passive: false });
        canvasElement.addEventListener('touchmove', handleTouchMove, { passive: false });
        canvasElement.addEventListener('touchend', handleDragEnd);
        
        // Visual feedback
        canvasElement.style.cursor = 'grab';
    });
}

function handleDragStart(e) {
    if (isSpinning) return;
    
    e.preventDefault();
    isDragging = true;
    
    const rect = e.target.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const mouseX = e.clientX - rect.left - centerX;
    const mouseY = e.clientY - rect.top - centerY;
    
    startAngle = Math.atan2(mouseY, mouseX);
    dragStartAngle = currentRotation;
    lastDragAngle = startAngle;
    lastDragTime = Date.now();
    dragHistory = [];
    dragVelocity = 0;
    
    // Parar qualquer momentum existente
    if (momentumTimer) {
        clearInterval(momentumTimer);
        momentumTimer = null;
    }
    
    e.target.style.cursor = 'grabbing';
    
    if (debugMode) {
        console.log('🖱️ Iniciando arrasto da roleta');
    }
}

function handleTouchStart(e) {
    if (isSpinning) return;
    
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousedown', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    handleDragStart({...mouseEvent, target: e.target, preventDefault: () => {}});
}

function handleDragMove(e) {
    if (!isDragging || isSpinning) return;
    
    e.preventDefault();
    
    const rect = e.target.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const mouseX = e.clientX - rect.left - centerX;
    const mouseY = e.clientY - rect.top - centerY;
    
    const currentAngle = Math.atan2(mouseY, mouseX);
    const currentTime = Date.now();
    
    // Calcular diferença angular
    let angleDiff = currentAngle - lastDragAngle;
    
    // Normalizar diferença angular para evitar saltos de 2π
    while (angleDiff > Math.PI) angleDiff -= 2 * Math.PI;
    while (angleDiff < -Math.PI) angleDiff += 2 * Math.PI;
    
    // Calcular velocidade angular (radianos por ms)
    const timeDiff = currentTime - lastDragTime;
    if (timeDiff > 0) {
        const velocity = angleDiff / timeDiff;
        
        // Adicionar ao histórico para calcular média
        dragHistory.push({ velocity, time: currentTime });
        
        // Manter apenas os últimos 100ms de histórico
        dragHistory = dragHistory.filter(entry => currentTime - entry.time < 100);
        
        // Calcular velocidade média
        if (dragHistory.length > 0) {
            dragVelocity = dragHistory.reduce((sum, entry) => sum + entry.velocity, 0) / dragHistory.length;
        }
    }
    
    // Aplicar rotação
    const totalAngleDiff = currentAngle - startAngle;
    currentRotation = dragStartAngle + (totalAngleDiff * 180 / Math.PI);
    
    // Atualizar valores para próxima iteração
    lastDragAngle = currentAngle;
    lastDragTime = currentTime;
    
    // Desenhar a roleta atualizada
    drawWheel();
    if (document.getElementById('fullscreenOverlay').classList.contains('show')) {
        drawFullscreenWheel();
    }
    
    if (debugMode) {
        console.log(`🖱️ Arrastando: ${currentRotation.toFixed(2)}° | Velocidade: ${(dragVelocity * 1000).toFixed(2)} rad/s`);
    }
}

function handleTouchMove(e) {
    if (!isDragging || isSpinning) return;
    
    e.preventDefault();
    const touch = e.touches[0];
    const mouseEvent = new MouseEvent('mousemove', {
        clientX: touch.clientX,
        clientY: touch.clientY
    });
    handleDragMove({...mouseEvent, target: e.target, preventDefault: () => {}});
}

function handleDragEnd(e) {
    if (!isDragging) return;
    
    isDragging = false;
    e.target.style.cursor = 'grab';
    
    // Aplicar momentum baseado na velocidade de arrastar
    const minVelocity = 0.001; // Velocidade mínima para iniciar momentum
    const maxVelocity = 0.5;   // Velocidade máxima para evitar giros muito rápidos
    
    if (Math.abs(dragVelocity) > minVelocity) {
        // Limitar velocidade máxima
        let momentumVelocity = Math.max(-maxVelocity, Math.min(maxVelocity, dragVelocity));
        
        // Converter para graus por frame (assumindo 60fps)
        momentumVelocity = momentumVelocity * (180 / Math.PI) * (1000 / 60);
        
        if (debugMode) {
            console.log(`🚀 Iniciando momentum: ${momentumVelocity.toFixed(2)}°/frame`);
        }
        
        // Iniciar animação de momentum
        startMomentumSpin(momentumVelocity);
    } else {
        if (debugMode) {
            console.log('🖱️ Finalizando arrasto - velocidade insuficiente para momentum');
        }
    }
    
    // Reset valores
    startAngle = 0;
    dragStartAngle = 0;
    dragVelocity = 0;
    dragHistory = [];
}

// 🚀 FUNCIONALIDADE DE MOMENTUM APÓS ARRASTAR
function startMomentumSpin(initialVelocity) {
    if (momentumTimer) {
        clearInterval(momentumTimer);
    }
    
    let velocity = initialVelocity;
    const friction = 0.98; // Fator de desaceleração (0.98 = 2% de redução por frame)
    const minVelocity = 0.1; // Velocidade mínima antes de parar
    
    // Alterar cursor para indicar movimento
    const canvas = document.getElementById('wheelCanvas');
    const fullscreenCanvas = document.getElementById('fullscreenCanvas');
    if (canvas) canvas.style.cursor = 'progress';
    if (fullscreenCanvas) fullscreenCanvas.style.cursor = 'progress';
    
    momentumTimer = setInterval(() => {
        // Aplicar rotação
        currentRotation += velocity;
        
        // Desenhar roleta
        drawWheel();
        if (document.getElementById('fullscreenOverlay').classList.contains('show')) {
            drawFullscreenWheel();
        }
        
        // Aplicar fricção
        velocity *= friction;
        
        // Parar quando velocidade fica muito baixa
        if (Math.abs(velocity) < minVelocity) {
            clearInterval(momentumTimer);
            momentumTimer = null;
            
            // Restaurar cursor
            if (canvas) canvas.style.cursor = 'grab';
            if (fullscreenCanvas) fullscreenCanvas.style.cursor = 'grab';
            
            if (debugMode) {
                console.log('🛑 Momentum finalizado');
            }
        }
    }, 1000 / 60); // 60 FPS
}

// CSS dinâmico
const style = document.createElement('style');
style.textContent = `
    @keyframes confettiFall {
        to {
            transform: translateY(100vh) rotate(360deg);
            opacity: 0;
        }
    }
    
    /* Animações adicionais para tela cheia */
    .fullscreen-overlay canvas {
        filter: drop-shadow(0 0 30px rgba(255, 255, 255, 0.3));
    }
    
    .fullscreen-overlay.spinning canvas {
        animation: wheelSpinGlow 4s ease-out;
    }
    
    @keyframes wheelSpinGlow {
        0% { filter: drop-shadow(0 0 30px rgba(255, 255, 255, 0.3)); }
        50% { filter: drop-shadow(0 0 60px rgba(74, 85, 104, 0.8)); }
        100% { filter: drop-shadow(0 0 30px rgba(255, 255, 255, 0.3)); }
    }
    
    /* 🛠️ ESTILOS DO PAINEL DE DEBUG */
    .debug-panel {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 90%;
        max-width: 600px;
        max-height: 80vh;
        background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
        border: 2px solid #4a5568;
        border-radius: 15px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
        z-index: 10000;
        overflow: hidden;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .debug-header {
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid #718096;
    }
    
    .debug-header h3 {
        margin: 0;
        color: #f7fafc;
        font-size: 18px;
        font-weight: bold;
    }
    
    .debug-header button {
        background: #e53e3e;
        color: white;
        border: none;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 16px;
        font-weight: bold;
        transition: all 0.2s ease;
    }
    
    .debug-header button:hover {
        background: #c53030;
        transform: scale(1.1);
    }
    
    .debug-content {
        padding: 20px;
        max-height: calc(80vh - 80px);
        overflow-y: auto;
        color: #e2e8f0;
    }
    
    .debug-section {
        margin-bottom: 25px;
        padding: 15px;
        background: rgba(74, 85, 104, 0.2);
        border-radius: 10px;
        border-left: 4px solid #4a5568;
    }
    
    .debug-section h4 {
        margin: 0 0 15px 0;
        color: #f7fafc;
        font-size: 16px;
        font-weight: bold;
        border-bottom: 1px solid #718096;
        padding-bottom: 8px;
    }
    
    .debug-section label {
        display: block;
        margin-bottom: 8px;
        color: #cbd5e0;
        font-weight: 500;
    }
    
    .debug-section select,
    .debug-section input[type="text"] {
        width: 100%;
        padding: 10px;
        background: #1a202c;
        border: 1px solid #4a5568;
        border-radius: 8px;
        color: #f7fafc;
        font-size: 14px;
        margin-bottom: 10px;
        transition: border-color 0.2s ease;
    }
    
    .debug-section select:focus,
    .debug-section input[type="text"]:focus {
        outline: none;
        border-color: #63b3ed;
        box-shadow: 0 0 0 3px rgba(99, 179, 237, 0.1);
    }
    
    .debug-btn {
        background: linear-gradient(135deg, #4a5568 0%, #2d3748 100%);
        color: #f7fafc;
        border: 1px solid #718096;
        padding: 8px 15px;
        border-radius: 8px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 500;
        margin: 5px 5px 5px 0;
        transition: all 0.2s ease;
        display: inline-block;
    }
    
    .debug-btn:hover {
        background: linear-gradient(135deg, #718096 0%, #4a5568 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }
    
    .debug-btn.danger {
        background: linear-gradient(135deg, #e53e3e 0%, #c53030 100%);
        border-color: #fc8181;
    }
    
    .debug-btn.danger:hover {
        background: linear-gradient(135deg, #c53030 0%, #9c2626 100%);
    }
    
    .debug-btn.warning {
        background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
        border-color: #f6ad55;
    }
    
    .debug-btn.warning:hover {
        background: linear-gradient(135deg, #dd6b20 0%, #c05621 100%);
    }
    
    .debug-info p {
        margin: 8px 0;
        padding: 8px;
        background: rgba(26, 32, 44, 0.5);
        border-radius: 6px;
        font-size: 14px;
        border-left: 3px solid #4a5568;
    }
    
    .debug-info strong {
        color: #63b3ed;
    }
    
    /* Scrollbar personalizada */
    .debug-content::-webkit-scrollbar {
        width: 8px;
    }
    
    .debug-content::-webkit-scrollbar-track {
        background: #1a202c;
        border-radius: 4px;
    }
    
    .debug-content::-webkit-scrollbar-thumb {
        background: #4a5568;
        border-radius: 4px;
    }
    
    .debug-content::-webkit-scrollbar-thumb:hover {
        background: #718096;
    }
    
    /* Animação de entrada */
    .debug-panel {
        animation: debugPanelEntry 0.3s ease-out;
    }
    
    @keyframes debugPanelEntry {
        0% {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.8);
        }
        100% {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
        }
    }
    
    /* 👤 Estilo para exibição do próximo colaborador */
    #nextEmployeeDisplay {
        background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
        color: #ffffff !important;
        font-weight: bold;
        font-size: 18px !important;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.8);
        padding: 12px 25px;
        border-radius: 25px;
        box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);
        display: inline-block;
        border: 2px solid #68d391;
        margin: 10px 0;
    }
    
    /* 🎮 Botões da tela cheia */
    button:disabled {
        opacity: 0.6;
        cursor: not-allowed !important;
        transform: none !important;
    }
    
    button:not(:disabled):hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }
`;
document.head.appendChild(style);

// Inicializar

init();
