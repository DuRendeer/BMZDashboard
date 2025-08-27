// Dashboard functions - Global scope

// Sync function
function syncData() {
    console.log('syncData called'); // Debug
    const syncIcon = document.getElementById('sync-icon');
    const syncIconBtn = document.getElementById('sync-icon-btn');
    
    if (syncIcon) syncIcon.classList.add('fa-spin');
    if (syncIconBtn) syncIconBtn.classList.add('fa-spin');
    
    fetch('sync.php?action=sync')
        .then(response => response.json())
        .then(data => {
            if (syncIcon) syncIcon.classList.remove('fa-spin');
            if (syncIconBtn) syncIconBtn.classList.remove('fa-spin');
            
            if (data.success) {
                alert('Sincronização concluída! ' + (data.message || ''));
                location.reload();
            } else {
                alert('Erro na sincronização: ' + (data.error || 'Erro desconhecido'));
            }
        })
        .catch(error => {
            if (syncIcon) syncIcon.classList.remove('fa-spin');
            if (syncIconBtn) syncIconBtn.classList.remove('fa-spin');
            alert('Erro de conexão: ' + error.message);
        });
}

// Show user details function
function showUserDetails(userId) {
    console.log('showUserDetails called for user:', userId); // Debug
    const modal = new bootstrap.Modal(document.getElementById('userModal'));
    const modalBody = document.getElementById('userModalBody');
    
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Load user details
    fetch(`user_details.php?id=${userId}`)
        .then(response => response.text())
        .then(html => {
            modalBody.innerHTML = html;
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Erro ao carregar dados: ${error.message}
                </div>
            `;
        });
}

// Global variables
let animatedList, magicBento, circularGallery, aurora;
let dashboardData, filteredUsers;
let currentUserId = null;

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Dashboard loading...'); // Debug
    
    // Load data
    dashboardData = JSON.parse(document.getElementById('dashboard-data').textContent);
    filteredUsers = [...dashboardData.users];
    
    // Initialize Aurora
    initializeAurora();
    
    // Initialize components
    initializeComponents();
    
    // Initialize navigation
    initializeNavigation();
    
    // Initialize filters
    initializeFilters();
    
    // Start animations
    animateCounters();
    
    console.log('Dashboard loaded successfully'); // Debug
});

function initializeAurora() {
    const auroraContainer = document.getElementById('aurora-container');
    if (auroraContainer && window.Aurora) {
        aurora = new Aurora(auroraContainer, dashboardData.auroraConfig);
    }
}

function initializeComponents() {
    // MagicBento
    const bentoContainer = document.getElementById('magic-bento-container');
    if (bentoContainer && window.MagicBento) {
        magicBento = new MagicBento(bentoContainer, {
            cards: dashboardData.bentoCards,
            enableStars: true,
            enableSpotlight: true,
            enableBorderGlow: true,
            clickEffect: true
        });
    }
    
    // AnimatedList
    const listContainer = document.getElementById('animated-list-container');
    if (listContainer && window.AnimatedList) {
        animatedList = new AnimatedList(listContainer, {
            items: filteredUsers,
            onItemSelect: handleUserSelect,
            showGradients: true,
            enableArrowNavigation: true
        });
    }
    
    // CircularGallery
    const galleryContainer = document.getElementById('circular-gallery-container');
    if (galleryContainer && window.CircularGallery) {
        circularGallery = new CircularGallery(galleryContainer, {
            items: dashboardData.userPhotos,
            bend: 3,
            textColor: '#ffffff',
            borderRadius: 0.05
        });
    }
}

function initializeNavigation() {
    const navItems = document.querySelectorAll('.nav-item[data-section]');
    console.log('Found nav items:', navItems.length); // Debug
    
    navItems.forEach((item, index) => {
        console.log('Setting up nav item:', index, item.dataset.section); // Debug
        item.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('Nav item clicked:', this.dataset.section); // Debug
            
            const targetSection = this.dataset.section;
            showSection(targetSection);
            
            // Update active state
            navItems.forEach(nav => nav.classList.remove('active'));
            this.classList.add('active');
        });
    });
}

function showSection(sectionName) {
    const sections = document.querySelectorAll('.section');
    sections.forEach(section => {
        section.style.display = 'none';
        section.style.opacity = '0';
    });
    
    const targetSection = document.getElementById(sectionName);
    if (targetSection) {
        targetSection.style.display = 'block';
        targetSection.style.opacity = '1';
        targetSection.style.animation = 'fadeInUp 0.6s ease-out forwards';
    }
    
    console.log('Showing section:', sectionName); // Debug
}

function initializeFilters() {
    const performanceFilter = document.getElementById('performance-filter');
    const userFilter = document.getElementById('user-filter');
    
    if (performanceFilter) performanceFilter.addEventListener('change', applyFilters);
    if (userFilter) userFilter.addEventListener('change', applyFilters);
}

function applyFilters() {
    const performanceFilter = document.getElementById('performance-filter');
    const userFilter = document.getElementById('user-filter');
    
    const performanceLevel = performanceFilter ? performanceFilter.value : '';
    const userId = userFilter ? userFilter.value : '';
    
    filteredUsers = dashboardData.users.filter(user => {
        let matchesPerformance = true;
        let matchesUser = true;
        
        if (performanceLevel) {
            const progress = user.progress;
            switch(performanceLevel) {
                case 'excellent': matchesPerformance = progress >= 100; break;
                case 'very_good': matchesPerformance = progress >= 75 && progress < 100; break;
                case 'good': matchesPerformance = progress >= 50 && progress < 75; break;
                case 'regular': matchesPerformance = progress >= 25 && progress < 50; break;
                case 'critical': matchesPerformance = progress < 25; break;
            }
        }
        
        if (userId) {
            matchesUser = user.user_id == userId;
        }
        
        return matchesPerformance && matchesUser;
    });
    
    // Update AnimatedList
    if (animatedList) {
        animatedList.updateItems(filteredUsers);
    }
}

function handleUserSelect(user, index) {
    currentUserId = user.user_id;
    showUserPerformance(user);
    
    // Switch to performance section
    showSection('performance');
    
    // Update nav
    document.querySelectorAll('.nav-item').forEach(nav => nav.classList.remove('active'));
    const perfNav = document.querySelector('[data-section="performance"]');
    if (perfNav) perfNav.classList.add('active');
}

function showUserPerformance(user) {
    const container = document.getElementById('performance-details');
    
    const progressPercent = Math.min(user.progress, 100);
    const dashOffset = 283 - (283 * progressPercent / 100);
    
    container.innerHTML = `
        <div class="row">
            <div class="col-md-4 text-center">
                <div class="user-avatar mb-3" style="width: 120px; height: 120px; font-size: 48px; margin: 0 auto;">
                    ${user.avatar}
                </div>
                <h3>${user.name}</h3>
                <p class="text-muted">${user.subtitle}</p>
                <span class="badge ${user.badgeClass} px-3 py-2">${user.badge}</span>
            </div>
            
            <div class="col-md-4 text-center">
                <h5 class="mb-3">Progresso da Meta</h5>
                <div class="position-relative d-inline-block">
                    <svg width="150" height="150" class="progress-ring">
                        <defs>
                            <linearGradient id="gradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                <stop offset="0%" style="stop-color:#3A29FF;stop-opacity:1" />
                                <stop offset="50%" style="stop-color:#FF94B4;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#FF3232;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        <circle class="progress-circle" cx="75" cy="75" r="45"></circle>
                        <circle class="progress-circle active" cx="75" cy="75" r="45" 
                                style="--dash-offset: ${dashOffset}"></circle>
                    </svg>
                    <div class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="h4 mb-0">${progressPercent.toFixed(1)}%</div>
                        <small class="text-muted">da meta</small>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <h5 class="mb-3">Estatísticas</h5>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Pontos Obtidos</span>
                        <strong class="text-primary">${user.points}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Meta Mensal</span>
                        <strong class="text-warning">${dashboardData.goals.monthly}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Faltam</span>
                        <strong class="text-info">${Math.max(0, dashboardData.goals.monthly - user.points)}</strong>
                    </div>
                </div>
                
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Tarefas Concluídas</span>
                        <strong class="text-success">${user.tasks}</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span>Meta de Tarefas</span>
                        <strong class="text-warning">${dashboardData.goals.tasks}</strong>
                    </div>
                </div>
                
                <button class="btn btn-primary w-100 aurora-button" onclick="showUserDetails(${user.user_id})">
                    <i class="fas fa-chart-line me-2"></i>
                    Ver Detalhes Completos
                </button>
            </div>
        </div>
    `;
}

function animateCounters() {
    const counters = document.querySelectorAll('.stat-value[data-count]');
    
    counters.forEach(counter => {
        const target = parseInt(counter.dataset.count);
        const increment = target / 100;
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                counter.textContent = target.toLocaleString();
                clearInterval(timer);
            } else {
                counter.textContent = Math.ceil(current).toLocaleString();
            }
        }, 20);
    });
}

// Resize handler
window.addEventListener('resize', function() {
    if (circularGallery) {
        circularGallery.onResize();
    }
});

// Expose functions globally
window.syncData = syncData;
window.showUserDetails = showUserDetails;