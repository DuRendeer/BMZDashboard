class MagicBento {
    constructor(container, options = {}) {
        this.container = container;
        this.options = {
            textAutoHide: options.textAutoHide !== false,
            enableStars: options.enableStars !== false,
            enableSpotlight: options.enableSpotlight !== false,
            enableBorderGlow: options.enableBorderGlow !== false,
            enableTilt: options.enableTilt !== false,
            enableMagnetism: options.enableMagnetism !== false,
            clickEffect: options.clickEffect !== false,
            spotlightRadius: options.spotlightRadius || 300,
            particleCount: options.particleCount || 12,
            glowColor: options.glowColor || "132, 0, 255",
            cards: options.cards || this.getDefaultCards(),
            ...options
        };
        
        this.spotlight = null;
        this.isInsideSection = false;
        this.particles = [];
        this.isMobile = window.innerWidth <= 768;
        
        this.init();
    }
    
    getDefaultCards() {
        return [
            {
                color: "#060010",
                title: "Analytics",
                description: "Track user behavior and performance metrics",
                label: "Insights",
                chart: "bar"
            },
            {
                color: "#060010", 
                title: "Dashboard",
                description: "Centralized data view with real-time updates",
                label: "Overview",
                chart: "line"
            },
            {
                color: "#060010",
                title: "Users",
                description: "Manage team members and permissions",
                label: "Team",
                chart: "doughnut"
            },
            {
                color: "#060010",
                title: "Goals",
                description: "Track progress towards objectives",
                label: "Progress",
                chart: "progress"
            },
            {
                color: "#060010",
                title: "Reports",
                description: "Generate detailed performance reports",
                label: "Reports",
                chart: "radar"
            },
            {
                color: "#060010",
                title: "Settings",
                description: "Configure system preferences",
                label: "Config",
                chart: "gauge"
            }
        ];
    }
    
    init() {
        this.createStructure();
        this.setupSpotlight();
        this.bindEvents();
        this.addResizeListener();
    }
    
    createStructure() {
        this.container.className = 'magic-bento-container';
        this.container.innerHTML = `
            <div class="card-grid bento-section">
                ${this.options.cards.map((card, index) => this.createCardHTML(card, index)).join('')}
            </div>
        `;
        
        this.gridElement = this.container.querySelector('.card-grid');
    }
    
    createCardHTML(card, index) {
        const baseClassName = `card ${this.options.textAutoHide ? "card--text-autohide" : ""} ${this.options.enableBorderGlow ? "card--border-glow" : ""}`;
        
        return `
            <div class="${baseClassName} particle-container" 
                 data-index="${index}"
                 style="background-color: ${card.color}; --glow-color: ${this.options.glowColor};">
                <div class="card__header">
                    <div class="card__label">${card.label}</div>
                    ${this.createChartIcon(card.chart)}
                </div>
                <div class="card__content">
                    <h2 class="card__title">${card.title}</h2>
                    <p class="card__description">${card.description}</p>
                    ${card.chart ? this.createMiniChart(card.chart, index) : ''}
                </div>
            </div>
        `;
    }
    
    createChartIcon(chartType) {
        const icons = {
            bar: '<i class="fas fa-chart-bar"></i>',
            line: '<i class="fas fa-chart-line"></i>',
            doughnut: '<i class="fas fa-chart-pie"></i>',
            progress: '<i class="fas fa-tasks"></i>',
            radar: '<i class="fas fa-spider-web"></i>',
            gauge: '<i class="fas fa-tachometer-alt"></i>'
        };
        return `<div class="card__icon">${icons[chartType] || icons.bar}</div>`;
    }
    
    createMiniChart(chartType, index) {
        const randomValue = Math.floor(Math.random() * 100) + 1;
        const randomValues = Array.from({length: 5}, () => Math.floor(Math.random() * 100));
        
        switch(chartType) {
            case 'progress':
                return `
                    <div class="mini-chart progress-chart">
                        <div class="progress-bar" style="width: ${randomValue}%"></div>
                        <span class="progress-text">${randomValue}%</span>
                    </div>
                `;
            case 'bar':
                return `
                    <div class="mini-chart bar-chart">
                        ${randomValues.map(val => 
                            `<div class="bar" style="height: ${val}%"></div>`
                        ).join('')}
                    </div>
                `;
            case 'line':
                const points = randomValues.map((val, i) => `${i * 25},${100 - val}`).join(' ');
                return `
                    <div class="mini-chart line-chart">
                        <svg viewBox="0 0 100 100" class="line-svg">
                            <polyline points="${points}" stroke="rgba(${this.options.glowColor}, 0.8)" 
                                     stroke-width="2" fill="none"/>
                        </svg>
                    </div>
                `;
            default:
                return '';
        }
    }
    
    setupSpotlight() {
        if (!this.options.enableSpotlight || this.isMobile) return;
        
        this.spotlight = document.createElement('div');
        this.spotlight.className = 'global-spotlight';
        this.spotlight.style.cssText = `
            position: fixed;
            width: 800px;
            height: 800px;
            border-radius: 50%;
            pointer-events: none;
            background: radial-gradient(circle,
                rgba(${this.options.glowColor}, 0.15) 0%,
                rgba(${this.options.glowColor}, 0.08) 15%,
                rgba(${this.options.glowColor}, 0.04) 25%,
                rgba(${this.options.glowColor}, 0.02) 40%,
                rgba(${this.options.glowColor}, 0.01) 65%,
                transparent 70%
            );
            z-index: 200;
            opacity: 0;
            transform: translate(-50%, -50%);
            mix-blend-mode: screen;
        `;
        document.body.appendChild(this.spotlight);
    }
    
    bindEvents() {
        document.addEventListener('mousemove', (e) => this.handleMouseMove(e));
        document.addEventListener('mouseleave', () => this.handleMouseLeave());
        
        this.container.querySelectorAll('.card').forEach((card, index) => {
            this.setupCardEvents(card, index);
        });
    }
    
    setupCardEvents(card, index) {
        let isHovered = false;
        let particles = [];
        
        card.addEventListener('mouseenter', () => {
            isHovered = true;
            
            if (this.options.enableStars && !this.isMobile) {
                this.animateParticles(card, particles);
            }
            
            if (this.options.enableTilt && !this.isMobile) {
                this.applyTilt(card, 5, 5);
            }
        });
        
        card.addEventListener('mouseleave', () => {
            isHovered = false;
            this.clearParticles(particles);
            
            if (this.options.enableTilt && !this.isMobile) {
                this.resetTilt(card);
            }
            
            if (this.options.enableMagnetism && !this.isMobile) {
                this.resetMagnetism(card);
            }
        });
        
        card.addEventListener('mousemove', (e) => {
            if (this.isMobile) return;
            
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            
            if (this.options.enableTilt) {
                const rotateX = ((y - centerY) / centerY) * -10;
                const rotateY = ((x - centerX) / centerX) * 10;
                this.applyTilt(card, rotateX, rotateY);
            }
            
            if (this.options.enableMagnetism) {
                const magnetX = (x - centerX) * 0.05;
                const magnetY = (y - centerY) * 0.05;
                this.applyMagnetism(card, magnetX, magnetY);
            }
        });
        
        card.addEventListener('click', (e) => {
            if (this.options.clickEffect && !this.isMobile) {
                this.createRippleEffect(card, e);
            }
            
            // Emit custom event
            card.dispatchEvent(new CustomEvent('cardClick', { 
                detail: { index, card: this.options.cards[index] }
            }));
        });
    }
    
    handleMouseMove(e) {
        if (!this.spotlight) return;
        
        const section = this.container;
        const rect = section.getBoundingClientRect();
        const mouseInside = e.clientX >= rect.left && e.clientX <= rect.right &&
                           e.clientY >= rect.top && e.clientY <= rect.bottom;
        
        this.isInsideSection = mouseInside;
        const cards = this.container.querySelectorAll('.card');
        
        if (!mouseInside) {
            this.spotlight.style.opacity = '0';
            cards.forEach(card => {
                card.style.setProperty('--glow-intensity', '0');
            });
            return;
        }
        
        const proximity = this.options.spotlightRadius * 0.5;
        const fadeDistance = this.options.spotlightRadius * 0.75;
        let minDistance = Infinity;
        
        cards.forEach(card => {
            const cardRect = card.getBoundingClientRect();
            const centerX = cardRect.left + cardRect.width / 2;
            const centerY = cardRect.top + cardRect.height / 2;
            const distance = Math.hypot(e.clientX - centerX, e.clientY - centerY) - 
                           Math.max(cardRect.width, cardRect.height) / 2;
            const effectiveDistance = Math.max(0, distance);
            
            minDistance = Math.min(minDistance, effectiveDistance);
            
            let glowIntensity = 0;
            if (effectiveDistance <= proximity) {
                glowIntensity = 1;
            } else if (effectiveDistance <= fadeDistance) {
                glowIntensity = (fadeDistance - effectiveDistance) / (fadeDistance - proximity);
            }
            
            this.updateCardGlow(card, e.clientX, e.clientY, glowIntensity);
        });
        
        this.spotlight.style.left = e.clientX + 'px';
        this.spotlight.style.top = e.clientY + 'px';
        
        const targetOpacity = minDistance <= proximity ? 0.8 : 
                             minDistance <= fadeDistance ? 
                             ((fadeDistance - minDistance) / (fadeDistance - proximity)) * 0.8 : 0;
        
        this.spotlight.style.opacity = targetOpacity;
    }
    
    handleMouseLeave() {
        this.isInsideSection = false;
        if (this.spotlight) {
            this.spotlight.style.opacity = '0';
        }
        this.container.querySelectorAll('.card').forEach(card => {
            card.style.setProperty('--glow-intensity', '0');
        });
    }
    
    updateCardGlow(card, mouseX, mouseY, intensity) {
        const rect = card.getBoundingClientRect();
        const relativeX = ((mouseX - rect.left) / rect.width) * 100;
        const relativeY = ((mouseY - rect.top) / rect.height) * 100;
        
        card.style.setProperty('--glow-x', `${relativeX}%`);
        card.style.setProperty('--glow-y', `${relativeY}%`);
        card.style.setProperty('--glow-intensity', intensity.toString());
        card.style.setProperty('--glow-radius', `${this.options.spotlightRadius}px`);
    }
    
    animateParticles(card, particles) {
        for (let i = 0; i < this.options.particleCount; i++) {
            setTimeout(() => {
                const particle = this.createParticle();
                card.appendChild(particle);
                particles.push(particle);
                
                // Animate particle
                const x = (Math.random() - 0.5) * 100;
                const y = (Math.random() - 0.5) * 100;
                
                particle.style.animation = `particleFloat 2s ease-in-out infinite`;
                particle.style.transform = `translate(${x}px, ${y}px)`;
                
                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                    const index = particles.indexOf(particle);
                    if (index > -1) particles.splice(index, 1);
                }, 2000);
            }, i * 100);
        }
    }
    
    createParticle() {
        const particle = document.createElement('div');
        particle.className = 'particle';
        particle.style.cssText = `
            position: absolute;
            width: 4px;
            height: 4px;
            border-radius: 50%;
            background: rgba(${this.options.glowColor}, 1);
            box-shadow: 0 0 6px rgba(${this.options.glowColor}, 0.6);
            pointer-events: none;
            z-index: 100;
            left: 50%;
            top: 50%;
            transform: translate(-50%, -50%);
        `;
        return particle;
    }
    
    clearParticles(particles) {
        particles.forEach(particle => {
            if (particle.parentNode) {
                particle.style.opacity = '0';
                particle.style.transform = 'scale(0)';
                setTimeout(() => {
                    if (particle.parentNode) {
                        particle.parentNode.removeChild(particle);
                    }
                }, 300);
            }
        });
        particles.length = 0;
    }
    
    applyTilt(card, rotateX, rotateY) {
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    }
    
    resetTilt(card) {
        card.style.transform = 'perspective(1000px) rotateX(0deg) rotateY(0deg)';
    }
    
    applyMagnetism(card, x, y) {
        const currentTransform = card.style.transform || '';
        const baseTransform = currentTransform.includes('perspective') ? currentTransform : 'perspective(1000px)';
        card.style.transform = `${baseTransform} translate(${x}px, ${y}px)`;
    }
    
    resetMagnetism(card) {
        const currentTransform = card.style.transform || '';
        if (currentTransform.includes('translate')) {
            card.style.transform = currentTransform.replace(/translate\([^)]*\)/g, '');
        }
    }
    
    createRippleEffect(card, e) {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const maxDistance = Math.max(
            Math.hypot(x, y),
            Math.hypot(x - rect.width, y),
            Math.hypot(x, y - rect.height),
            Math.hypot(x - rect.width, y - rect.height)
        );
        
        const ripple = document.createElement('div');
        ripple.style.cssText = `
            position: absolute;
            width: ${maxDistance * 2}px;
            height: ${maxDistance * 2}px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(${this.options.glowColor}, 0.4) 0%, rgba(${this.options.glowColor}, 0.2) 30%, transparent 70%);
            left: ${x - maxDistance}px;
            top: ${y - maxDistance}px;
            pointer-events: none;
            z-index: 1000;
            animation: rippleExpand 0.8s ease-out forwards;
        `;
        
        card.appendChild(ripple);
        setTimeout(() => ripple.remove(), 800);
    }
    
    addResizeListener() {
        window.addEventListener('resize', () => {
            this.isMobile = window.innerWidth <= 768;
        });
    }
    
    updateCards(newCards) {
        this.options.cards = newCards;
        this.createStructure();
        this.bindEvents();
    }
    
    destroy() {
        if (this.spotlight && this.spotlight.parentNode) {
            this.spotlight.parentNode.removeChild(this.spotlight);
        }
        document.removeEventListener('mousemove', this.handleMouseMove);
        document.removeEventListener('mouseleave', this.handleMouseLeave);
    }
}

// Export for global use
window.MagicBento = MagicBento;