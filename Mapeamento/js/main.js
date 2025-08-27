// Sistema de Mapeamento de Salas - JavaScript
let gridVisible = false;
let draggedElement = null;
let offset = { x: 0, y: 0 };
let mesasData = {}; // Será carregado do banco

// Inicialização
document.addEventListener('DOMContentLoaded', function() {
    loadMesasData();
    initDragAndDrop();
});

// Carregar dados das mesas do banco
function loadMesasData() {
    fetch('includes/get_mesas.php')
        .then(response => response.json())
        .then(data => {
            mesasData = data;
            updateMesasDisplay();
        })
        .catch(error => {
            console.error('Erro ao carregar dados:', error);
            alert('Erro ao carregar dados das mesas');
        });
}

// Atualizar display das mesas
function updateMesasDisplay() {
    const mesaBtns = document.querySelectorAll('.mesa-btn');
    mesaBtns.forEach(btn => {
        const mesaId = btn.getAttribute('data-mesa');
        const mesa = mesasData[mesaId];
        
        if (mesa) {
            btn.className = `mesa-btn ${mesa.status}`;
            btn.style.left = mesa.posicao_x + 'px';
            btn.style.top = mesa.posicao_y + 'px';
        }
    });
}

// Toggle da grade
function toggleGrid() {
    const grid = document.getElementById('grid-overlay');
    gridVisible = !gridVisible;
    
    if (gridVisible) {
        grid.classList.add('show');
        showGridCoordinates();
    } else {
        grid.classList.remove('show');
        removeGridCoordinates();
    }
}

// Mostrar coordenadas da grade
function showGridCoordinates() {
    const container = document.querySelector('.room-2d');
    
    for (let x = 0; x < 1600; x += 100) {
        for (let y = 0; y < 900; y += 100) {
            const coord = document.createElement('div');
            coord.className = 'grid-coordinates';
            coord.textContent = `${x},${y}`;
            coord.style.left = x + 'px';
            coord.style.top = y + 'px';
            container.appendChild(coord);
        }
    }
}

// Remover coordenadas da grade
function removeGridCoordinates() {
    const coords = document.querySelectorAll('.grid-coordinates');
    coords.forEach(coord => coord.remove());
}

// Mostrar modal da mesa
function showMesaModal(mesaId) {
    const mesa = mesasData[mesaId];
    if (!mesa) return;
    
    const modal = document.getElementById('mesaModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalContent = document.getElementById('modalContent');
    
    modalTitle.textContent = `Mesa ${mesaId}`;
    
    const statusClass = mesa.status === 'ocupada' ? 'status-ocupada' : 'status-livre';
    const statusText = mesa.status === 'ocupada' ? 'Ocupada' : 'Livre';
    
    let content = `
        <div class="status-badge ${statusClass}">${statusText}</div>
    `;
    
    if (mesa.status === 'ocupada') {
        content += `
            <div class="info-row">
                <span class="info-label">Funcionário:</span>
                <span class="info-value">${mesa.nome}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Função:</span>
                <span class="info-value">${mesa.funcao}</span>
            </div>
        `;
    } else {
        content += `
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value">Mesa disponível</span>
            </div>
        `;
    }
    
    content += `
        <div class="info-row">
            <span class="info-label">Setor:</span>
            <span class="info-value">${mesa.setor}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Turno:</span>
            <span class="info-value">${mesa.turno}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Horário de Trabalho:</span>
            <span class="info-value">${mesa.horario_inicio} às ${mesa.horario_fim}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Última atualização:</span>
            <span class="info-value">${new Date().toLocaleTimeString('pt-BR')}</span>
        </div>
        
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="editMesa('${mesaId}')">
                ✏️ Editar
            </button>
            <button class="btn btn-warning" onclick="transferMesa('${mesaId}')">
                🔄 Transferir
            </button>
            <button class="btn btn-danger" onclick="removeMesa('${mesaId}')">
                🗑️ Remover
            </button>
        </div>
    `;
    
    modalContent.innerHTML = content;
    modal.style.display = 'block';
}

// Editar mesa
function editMesa(mesaId) {
    const mesa = mesasData[mesaId];
    if (!mesa) return;
    
    const modalContent = document.getElementById('modalContent');
    modalContent.innerHTML = `
        <form id="editForm">
            <div class="form-group">
                <label for="nome">Nome do Funcionário:</label>
                <input type="text" id="nome" name="nome" value="${mesa.nome}" placeholder="Nome completo">
            </div>
            
            <div class="form-group">
                <label for="funcao">Função:</label>
                <input type="text" id="funcao" name="funcao" value="${mesa.funcao}" placeholder="Cargo/Função">
            </div>
            
            <div class="form-group">
                <label for="status">Status:</label>
                <select id="status" name="status">
                    <option value="ocupada" ${mesa.status === 'ocupada' ? 'selected' : ''}>Ocupada</option>
                    <option value="livre" ${mesa.status === 'livre' ? 'selected' : ''}>Livre</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="turno">Turno:</label>
                <select id="turno" name="turno">
                    <option value="Manhã" ${mesa.turno === 'Manhã' ? 'selected' : ''}>Manhã</option>
                    <option value="Tarde" ${mesa.turno === 'Tarde' ? 'selected' : ''}>Tarde</option>
                    <option value="Noite" ${mesa.turno === 'Noite' ? 'selected' : ''}>Noite</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="horario_inicio">Horário de Início:</label>
                <input type="time" id="horario_inicio" name="horario_inicio" value="${mesa.horario_inicio}">
            </div>
            
            <div class="form-group">
                <label for="horario_fim">Horário de Fim:</label>
                <input type="time" id="horario_fim" name="horario_fim" value="${mesa.horario_fim}">
            </div>
            
            <div class="action-buttons">
                <button type="button" class="btn btn-success" onclick="saveMesa('${mesaId}')">
                    💾 Salvar
                </button>
                <button type="button" class="btn btn-primary" onclick="showMesaModal('${mesaId}')">
                    ↩️ Voltar
                </button>
            </div>
        </form>
    `;
}

// Salvar alterações da mesa
function saveMesa(mesaId) {
    const form = document.getElementById('editForm');
    const formData = new FormData(form);
    formData.append('mesa_id', mesaId);
    formData.append('action', 'update');
    
    fetch('includes/manage_mesa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Mesa atualizada com sucesso!');
            loadMesasData();
            closeMesaModal();
        } else {
            alert('Erro ao atualizar mesa: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar alterações');
    });
}

// Transferir funcionário
function transferMesa(mesaId) {
    const mesa = mesasData[mesaId];
    if (!mesa || mesa.status !== 'ocupada') {
        alert('Apenas mesas ocupadas podem ser transferidas');
        return;
    }
    
    // Buscar mesas livres do mesmo setor
    const mesasLivres = Object.entries(mesasData)
        .filter(([id, m]) => m.status === 'livre' && m.setor === mesa.setor)
        .map(([id, m]) => id);
    
    if (mesasLivres.length === 0) {
        alert('Não há mesas livres disponíveis neste setor');
        return;
    }
    
    const modalContent = document.getElementById('modalContent');
    modalContent.innerHTML = `
        <h3>Transferir Funcionário</h3>
        <p><strong>Funcionário:</strong> ${mesa.nome} (${mesa.funcao})</p>
        <p><strong>Mesa atual:</strong> ${mesaId}</p>
        
        <div class="form-group">
            <label for="mesaDestino">Selecione a mesa de destino:</label>
            <select id="mesaDestino" name="mesaDestino">
                <option value="">-- Selecione uma mesa --</option>
                ${mesasLivres.map(id => `<option value="${id}">Mesa ${id}</option>`).join('')}
            </select>
        </div>
        
        <div class="action-buttons">
            <button type="button" class="btn btn-success" onclick="confirmTransfer('${mesaId}')">
                🔄 Confirmar Transferência
            </button>
            <button type="button" class="btn btn-primary" onclick="showMesaModal('${mesaId}')">
                ↩️ Cancelar
            </button>
        </div>
    `;
}

// Confirmar transferência
function confirmTransfer(mesaOrigem) {
    const mesaDestino = document.getElementById('mesaDestino').value;
    if (!mesaDestino) {
        alert('Selecione uma mesa de destino');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'transfer');
    formData.append('mesa_origem', mesaOrigem);
    formData.append('mesa_destino', mesaDestino);
    
    fetch('includes/manage_mesa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Funcionário transferido com sucesso!');
            loadMesasData();
            closeMesaModal();
        } else {
            alert('Erro na transferência: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao transferir funcionário');
    });
}

// Remover funcionário da mesa
function removeMesa(mesaId) {
    const mesa = mesasData[mesaId];
    if (!mesa || mesa.status !== 'ocupada') {
        alert('Apenas mesas ocupadas podem ser liberadas');
        return;
    }
    
    if (!confirm(`Deseja remover ${mesa.nome} da mesa ${mesaId}?`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('mesa_id', mesaId);
    
    fetch('includes/manage_mesa.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Funcionário removido com sucesso!');
            loadMesasData();
            closeMesaModal();
        } else {
            alert('Erro ao remover funcionário: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao remover funcionário');
    });
}

// Fechar modal
function closeMesaModal() {
    document.getElementById('mesaModal').style.display = 'none';
}

// Fechar modal clicando fora
window.onclick = function(event) {
    const modal = document.getElementById('mesaModal');
    if (event.target == modal) {
        modal.style.display = 'none';
    }
}

// Exportar posições
function exportPositions() {
    const buttons = document.querySelectorAll('.mesa-btn.draggable');
    let positions = {};
    
    buttons.forEach(btn => {
        const mesa = btn.getAttribute('data-mesa');
        positions[mesa] = {
            top: parseInt(btn.style.top),
            left: parseInt(btn.style.left)
        };
    });
    
    const output = JSON.stringify(positions, null, 2);
    console.log('Posições das mesas:', output);
    
    const textarea = document.createElement('textarea');
    textarea.value = output;
    document.body.appendChild(textarea);
    textarea.select();
    document.execCommand('copy');
    document.body.removeChild(textarea);
    
    alert('Posições das mesas copiadas para o clipboard!');
}

// Sistema de drag and drop
function initDragAndDrop() {
    const draggables = document.querySelectorAll('.mesa-btn.draggable');
    
    draggables.forEach(element => {
        element.addEventListener('mousedown', handleMouseDown);
        element.addEventListener('click', function(e) {
            if (!this.classList.contains('dragging')) {
                showMesaModal(this.getAttribute('data-mesa'));
            }
        });
    });
    
    document.addEventListener('mousemove', handleMouseMove);
    document.addEventListener('mouseup', handleMouseUp);
}

function handleMouseDown(e) {
    draggedElement = e.target;
    draggedElement.classList.add('dragging');
    
    const rect = draggedElement.getBoundingClientRect();
    const containerRect = draggedElement.parentElement.getBoundingClientRect();
    
    offset.x = e.clientX - rect.left;
    offset.y = e.clientY - rect.top;
    
    e.preventDefault();
    e.stopPropagation();
}

function handleMouseMove(e) {
    if (!draggedElement) return;
    
    const container = draggedElement.parentElement;
    const containerRect = container.getBoundingClientRect();
    
    let newLeft = e.clientX - containerRect.left - offset.x;
    let newTop = e.clientY - containerRect.top - offset.y;
    
    // Snap to grid if visible
    if (gridVisible) {
        newLeft = Math.round(newLeft / 50) * 50;
        newTop = Math.round(newTop / 50) * 50;
    }
    
    // Keep within bounds
    newLeft = Math.max(0, Math.min(newLeft, 1560));
    newTop = Math.max(0, Math.min(newTop, 860));
    
    draggedElement.style.left = newLeft + 'px';
    draggedElement.style.top = newTop + 'px';
}

function handleMouseUp(e) {
    if (draggedElement) {
        draggedElement.classList.remove('dragging');
        draggedElement = null;
    }
}