// Fazer a luz seguir o cursor do mouse
document.addEventListener('mousemove', function(e) {
    const container = document.querySelector('.container:before');
    const x = (e.clientX / window.innerWidth) * 100;
    const y = (e.clientY / window.innerHeight) * 100;
    
    // Atualizar a propriedade CSS customizada
    document.documentElement.style.setProperty('--mouse-x', x + '%');
    document.documentElement.style.setProperty('--mouse-y', y + '%');
    
    // Aplicar o clip-path seguindo o mouse
    const containerBefore = document.querySelector('.container');
    if (containerBefore) {
        containerBefore.style.setProperty('--clip-path', `circle(150px at ${x}% ${y}%)`);
    }
});

// Aplicar o clip-path via CSS custom property
const style = document.createElement('style');
style.textContent = `
    .container:before {
        clip-path: var(--clip-path, circle(150px at 50% 50%));
    }
`;
document.head.appendChild(style);

// Função de logout
function logout() {
    if (confirm('Deseja realmente sair do sistema?')) {
        window.location.href = '?logout=1';
    }
}