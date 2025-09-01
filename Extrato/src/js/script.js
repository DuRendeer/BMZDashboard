// Global variables
let currentPDFFile = null;
let currentPDF = null;
let extractedEmployees = [];
let processedPages = [];
let discordDatabase = new Map(); // Mapa nome -> discord_id
let codeDatabase = new Map(); // Mapa código -> {nome, discord_id}

const WEBHOOK_URL = "https://hook.us1.make.com/d7km249aggme5icqoq8t8a5urtj9nie8";
const BASE_URL = "https://bmzdashboard.shop/Extrato/";

// PDF.js configuration
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    initializeApp();
    loadDiscordDatabase();
});

function loadDiscordDatabase() {
    // Dados hardcoded do CSV - apenas com códigos conhecidos
    const csvData = [
        ["", "Leonardo Belin", "673943549175267364"],
        ["", "Diarle Lucas Medeiros", "1255335166222532618"], 
        ["", "Guilherme Zaiats", "691446462034214943"],
        ["80", "Adriano Kolitski", "1364225869480198177"],
        ["", "Alexander Nicolas Costa", "1359524108307533955"],
        ["", "Brenda Ohana Ferreira Souza", "1361729035130114149"],
        ["", "Bruna Aparecida Lukaski", "1361395357493235722"],
        ["", "Camile Nunes De Siqueira", "1361395789951139912"],
        ["", "Gisele Saplak", "1361397728990597364"],
        ["", "Hevilin Vitoria Machado Do Nascimento", "1361395897300422727"],
        ["", "Jamille Cristine Scheidt", "1361376412686225470"],
        ["", "Jessica Riffel Rother", "1361394759444332736"],
        ["", "Karen Alessandra Sochodolak", "1361398071019180112"],
        ["", "Lucas Eduardo Racelli Prestes Gomes", "1361399522411942120"],
        ["", "Margarete Dorak", "1361395146217754765"],
        ["", "Thamires Andrade", "1361395141914394976"],
        ["", "Gabriely Holodivski", "1361397256325955864"],
        ["", "Andrielli Alves Pereira", "1361339984837349407"],
        ["", "Kelita Larissa Alves Schultz", "1359173468788097246"],
        ["", "Carla Cristina Pacheco dos Santos", "1361381987910287410"],
        ["", "Henrique De Oliveira Leite", "1359208862254764085"],
        ["", "Bianca Staxiv Viviurka", "1361385825878675486"],
        ["", "Evair Goncalves De Oliveira", "1361413125756289247"],
        ["", "Liedson Winharski", "1361380831855251549"],
        ["", "Lucas Gabriel Ribeiro da Silva", "1361412636998238461"],
        ["", "Millene Taynara Leal do Nascimento", "1361412993492979815"],
        ["", "Amanda Kloster Painko", "1361411829095596294"],
        ["", "Ana Paula Melnik", "1359208640900235345"],
        ["", "Elaine Borges De Oliveira", "1361410488776523845"],
        ["", "Erica Okarenski", "1361410342768476271"],
        ["", "Fabio Petriw Urhen", "1361410249457795112"],
        ["", "Gessica Trzesniovski", "1361410514177233252"],
        ["", "Gisele Freitas Da Silva", "1362404886968275076"],
        ["", "Hilary Leonarda Erddmann", "1361412498607308940"],
        ["", "Janayna Freisleben", "1361410241194889356"],
        ["", "Jose Henrique Krokoch De Araujo", "1361414341835882566"],
        ["", "Joao Guilherme De Araujo", "1361411759604498595"],
        ["", "Maria Rita Lopes", "1361412739595112468"],
        ["", "Stephanie Nascimento Cobo Ribeiro", "1361412397654347796"],
        ["", "Leticia Guil Wolff", "1361670964168884425"],
        ["", "Delia Anahis Ochoa Espinoza", "1361403945091731608"],
        ["", "Maria Leticia Pototsky", "1361401815698116710"],
        ["", "Giliane Plodowski", "1361671739247165615"],
        ["71", "Eduardo Sochodolak", "1359191566165737612"],
        ["", "Leonardo Marconato", "1375124852058488924"],
        ["", "Rodrigo Garbachevski", "1361394542070337536"],
        ["", "Andrey Gustavo Boiko", "1371454821789601792"],
        ["", "Henrique Fernandes Gerei", "1373982787996090380"],
        ["", "Stefani Teixeira Biscaia", "1378045798172069920"],
        ["", "Gabriela Korchak", "1389216006270095472"],
        ["", "Rafael Lupepsiv Codolo", "1387510781171601541"]
        // Samuel Henrique Da Rocha Semzezyn - AGUARDANDO Discord ID correto
    ];
    
    console.log('Carregando banco de dados Discord hardcoded...');
    
    // Limpar bancos existentes
    discordDatabase.clear();
    codeDatabase.clear();
    
    // Carregar dados
    csvData.forEach(([codigo, nome, discordId]) => {
        const normalizedName = nome.trim().toUpperCase();
        const cleanDiscordId = discordId.trim();
        const cleanCode = codigo.trim();
        
        // Mapear por nome (fallback)
        discordDatabase.set(normalizedName, cleanDiscordId);
        
        // Mapear por código apenas se código não estiver vazio
        if (cleanCode) {
            codeDatabase.set(cleanCode, {
                nome: normalizedName,
                discord_id: cleanDiscordId
            });
        }
    });
    
    console.log(`Carregado ${discordDatabase.size} registros do Discord`);
    console.log(`Carregado ${codeDatabase.size} registros por código`);
    console.log('Primeiros 5 códigos:', Array.from(codeDatabase.entries()).slice(0, 5));
}

function initializeApp() {
    const fileInput = document.getElementById('fileInput');
    const uploadArea = document.getElementById('uploadArea');
    
    // File input change event
    fileInput.addEventListener('change', handleFileSelect);
    
    // Drag and drop events
    uploadArea.addEventListener('dragover', handleDragOver);
    uploadArea.addEventListener('dragleave', handleDragLeave);
    uploadArea.addEventListener('drop', handleDrop);
    uploadArea.addEventListener('click', () => fileInput.click());
}

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file && file.type === 'application/pdf') {
        processPDF(file);
    } else {
        showError('Por favor, selecione um arquivo PDF válido.');
    }
}

function handleDragOver(event) {
    event.preventDefault();
    event.currentTarget.classList.add('dragover');
}

function handleDragLeave(event) {
    event.currentTarget.classList.remove('dragover');
}

function handleDrop(event) {
    event.preventDefault();
    event.currentTarget.classList.remove('dragover');
    
    const files = event.dataTransfer.files;
    if (files.length > 0 && files[0].type === 'application/pdf') {
        processPDF(files[0]);
    } else {
        showError('Por favor, solte um arquivo PDF válido.');
    }
}

async function processPDF(file) {
    try {
        showSection('processingSection');
        
        // Store the original file for later use
        currentPDFFile = file;
        
        // Save original PDF to Inputs folder
        await saveInputPDF(file);
        
        // Read file as array buffer for pdf.js
        const arrayBuffer = await file.arrayBuffer();
        
        // Load PDF with pdf.js
        const pdf = await pdfjsLib.getDocument(arrayBuffer).promise;
        
        extractedEmployees = [];
        processedPages = [];
        
        // Extract text from each page and identify employees
        for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
            const page = await pdf.getPage(pageNum);
            const textContent = await page.getTextContent();
            
            // Extract text from page
            const pageText = textContent.items.map(item => item.str).join(' ');
            
            // Debug: Log page text
            console.log(`Página ${pageNum} texto:`, pageText.substring(0, 500));
            
            // Try to extract employee code first (more reliable)
            const employeeCode = extractEmployeeCode(pageText);
            
            // Try to extract employee name from header
            const employeeName = extractEmployeeName(pageText);
            
            // Debug: Log extracted data
            console.log(`Página ${pageNum} código extraído:`, employeeCode);
            console.log(`Página ${pageNum} nome extraído:`, employeeName);
            
            // Determine final employee data (prefer code over name)
            let finalEmployeeName = null;
            let discordId = null;
            
            if (employeeCode) {
                const codeData = codeDatabase.get(employeeCode);
                if (codeData) {
                    finalEmployeeName = codeData.nome;
                    discordId = codeData.discord_id;
                    console.log(`✅ Match por código ${employeeCode}: ${finalEmployeeName}`);
                }
            }
            
            // Fallback to name matching if code failed
            if (!finalEmployeeName && employeeName) {
                finalEmployeeName = employeeName;
                discordId = findDiscordIdByName(employeeName);
                console.log(`⚠️ Fallback para nome: ${employeeName}`);
            }
            
            if (finalEmployeeName) {
                // Check if this employee already exists
                let employee = extractedEmployees.find(emp => emp.name === finalEmployeeName);
                
                if (!employee) {
                    employee = {
                        name: finalEmployeeName,
                        pages: [],
                        selected: true,
                        code: employeeCode,
                        discord_id: discordId
                    };
                    extractedEmployees.push(employee);
                }
                
                employee.pages.push(pageNum);
                processedPages.push({
                    pageNum: pageNum,
                    employeeName: finalEmployeeName,
                    code: employeeCode,
                    text: pageText
                });
            }
        }
        
        if (extractedEmployees.length === 0) {
            showError('Não foi possível identificar funcionários no PDF. Verifique se o formato está correto.');
            showSection('uploadSection');
            return;
        }
        
        displayEmployees();
        
    } catch (error) {
        console.error('Erro ao processar PDF:', error);
        showError('Erro ao processar o PDF. Verifique se o arquivo não está corrompido.');
        showSection('uploadSection');
    }
}

function extractEmployeeName(pageText) {
    // Multiple patterns to extract employee names from different payroll formats
    const patterns = [
        // Pattern específico para este formato: Nome do funcionário seguido de C.C:
        /Nome do funcionário\s+C\.C:\s+\d+\s+([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]+?)\s+CBO/i,
        
        // Pattern para capturar nome em maiúsculas seguido de CBO
        /\s+([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]+?)\s+CBO\s*:/i,
        
        // Pattern para nome seguido de CPF com números específicos
        /([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]{10,50})\s+CPF\s+[\d\.\-]+/i,
        
        // Pattern para nome seguido de PIS
        /([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]{10,50})\s+PIS\s+[\d\.]+/i,
        
        // Pattern para nome seguido de CTPS
        /([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]{10,50})\s+CTPS\s+[\d\s]+/i,
        
        // Pattern para nome seguido de Admissão
        /([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]{10,50})\s+Admissão/i,
        
        // Pattern genérico para nome em maiúsculas entre códigos/números
        /(?:\d+\s+)([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]{8,50})(?=\s+(?:CBO|CPF|PIS|CTPS|Admissão))/i,
        
        // Backup patterns
        /(?:NOME|Nome):\s*([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]+?)(?:\s+CPF|$|\n|\s{3,})/i,
        /(?:FUNCIONÁRIO|Funcionário):\s*([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]+?)(?:\s+CPF|$|\n|\s{3,})/i,
        /(?:EMPREGADO|Empregado):\s*([A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô\s]+?)(?:\s+CPF|$|\n|\s{3,})/i
    ];
    
    console.log('Tentando extrair nome de:', pageText.substring(0, 200));
    
    for (let i = 0; i < patterns.length; i++) {
        const pattern = patterns[i];
        const match = pageText.match(pattern);
        console.log(`Pattern ${i + 1}:`, match ? match[1]?.trim() : 'não encontrou');
        
        if (match && match[1]) {
            const name = match[1].trim();
            // Validate name (at least 2 words, reasonable length)
            if (name.split(' ').length >= 2 && name.length >= 5 && name.length <= 80) {
                console.log('Nome válido encontrado:', name.toUpperCase());
                return name.toUpperCase();
            }
        }
    }
    
    // If no pattern matches, try to find the most likely name candidate
    const lines = pageText.split('\n').slice(0, 10); // Check first 10 lines
    for (const line of lines) {
        const words = line.trim().split(/\s+/);
        for (let i = 0; i < words.length - 1; i++) {
            const candidate = words.slice(i, Math.min(i + 4, words.length)).join(' ');
            if (/^[A-ZÁÊÇÃÀ][A-Za-záêçãàíúóô]+(?:\s+[A-Za-záêçãàíúóô]+){1,3}$/.test(candidate) && 
                candidate.length >= 5 && candidate.length <= 50) {
                return candidate.toUpperCase();
            }
        }
    }
    
    return null;
}

function extractEmployeeCode(pageText) {
    console.log('Tentando extrair código de:', pageText.substring(0, 300));
    
    // Pattern para capturar código C.C: seguido de número
    const patterns = [
        // Pattern principal: "C.C:" seguido de espaços e número
        /C\.C:\s*(\d+)/i,
        
        // Pattern alternativo: "CC:" ou "C C:" 
        /C\s*C:\s*(\d+)/i,
        
        // Pattern para "Código" seguido de número
        /Código\s*:?\s*(\d+)/i,
        
        // Pattern genérico para número depois de "funcionário"
        /funcionário\s+C\.C:\s*(\d+)/i
    ];
    
    for (let i = 0; i < patterns.length; i++) {
        const pattern = patterns[i];
        const match = pageText.match(pattern);
        console.log(`Pattern código ${i + 1}:`, match ? match[1] : 'não encontrou');
        
        if (match && match[1]) {
            const code = match[1].trim();
            console.log('Código válido encontrado:', code);
            return code;
        }
    }
    
    console.log('Nenhum código encontrado');
    return null;
}

async function saveInputPDF(file) {
    try {
        const formData = new FormData();
        formData.append('pdf', file);
        formData.append('action', 'upload-input');
        
        const response = await fetch('./src/pages/api.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ PDF original salvo em:', result.url);
        } else {
            console.error('❌ Erro ao salvar PDF original:', result.error);
            showError('Erro ao processar PDF');
        }
        
    } catch (error) {
        console.error('❌ Erro ao enviar PDF original:', error);
        showError('Erro ao processar PDF');
    }
}

async function saveHoleritePDF(employee, pdfBytes) {
    try {
        // Converter PDF bytes para base64
        const base64Data = btoa(String.fromCharCode(...new Uint8Array(pdfBytes)));
        
        const response = await fetch('./src/pages/api.php?action=save-holerite', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                nome: employee.name,
                pdfData: base64Data,
                codigo: employee.code
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            console.log('✅ Holerite salvo:', result.url);
            return result.url;
        } else {
            throw new Error(result.error);
        }
        
    } catch (error) {
        console.error('❌ Erro ao salvar holerite:', error);
        throw error;
    }
}

async function displayEmployees() {
    const employeesPreview = document.getElementById('employeesPreview');
    employeesPreview.innerHTML = '';
    
    // Show loading
    employeesPreview.innerHTML = '<div class="loading"><div class="spinner"></div><p>Gerando previews...</p></div>';
    
    for (let index = 0; index < extractedEmployees.length; index++) {
        const employee = extractedEmployees[index];
        
        try {
            // Generate PDF for this employee
            const pdfArrayBuffer = await currentPDFFile.arrayBuffer();
            const pdfData = new Uint8Array(pdfArrayBuffer);
            const pdfDoc = await PDFLib.PDFDocument.load(pdfData);
            const newPdf = await PDFLib.PDFDocument.create();
            
            // Copy pages for this employee
            const pageIndices = employee.pages.map(pageNum => pageNum - 1);
            const copiedPages = await newPdf.copyPages(pdfDoc, pageIndices);
            
            copiedPages.forEach(page => {
                newPdf.addPage(page);
            });
            
            // Generate PDF bytes
            const pdfBytes = await newPdf.save();
            
            // Save PDF to server immediately
            const savedUrl = await saveHoleritePDF(employee, pdfBytes);
            employee.savedUrl = savedUrl;
            
            // Create blob URL for preview
            const blob = new Blob([pdfBytes], { type: 'application/pdf' });
            const url = URL.createObjectURL(blob);
            employee.previewUrl = url;
            
            console.log(`✅ Holerite criado para ${employee.name}: ${savedUrl}`);
            
        } catch (error) {
            console.error(`❌ Erro ao gerar holerite para ${employee.name}:`, error);
        }
    }
    
    // Clear loading and show previews
    employeesPreview.innerHTML = '';
    
    extractedEmployees.forEach((employee, index) => {
        const previewCard = document.createElement('div');
        previewCard.className = 'employee-preview-card';
        // Use discord_id já armazenado no employee (mais confiável)
        const discordId = employee.discord_id || findDiscordIdByName(employee.name);
        const hasDiscord = discordId !== null;
        
        previewCard.innerHTML = `
            <div class="preview-header">
                <div class="employee-info">
                    <input type="checkbox" class="checkbox" id="employee-${index}" 
                           ${employee.selected ? 'checked' : ''} 
                           onchange="toggleEmployee(${index})">
                    <input type="text" class="edit-name-inline" value="${employee.name}" 
                           onchange="updateEmployeeName(${index}, this.value)"
                           placeholder="Nome do funcionário">
                    <span class="page-info">${employee.pages.length} página(s)</span>
                    ${employee.code ? `<span class="code-info">Código: ${employee.code}</span>` : ''}
                    ${hasDiscord ? 
                        `<span class="discord-status discord-found">✓ Discord: ${discordId}</span>` :
                        `<span class="discord-status discord-not-found">⚠ Discord não encontrado</span>`
                    }
                </div>
                <div class="action-buttons">
                    <button class="btn-approve" onclick="approveEmployee(${index})" 
                            ${!hasDiscord ? 'disabled title="Discord ID não encontrado"' : ''}>
                        ✓ Aprovar
                    </button>
                </div>
            </div>
            <div class="pdf-preview">
                ${employee.previewUrl ? 
                    `<iframe src="${employee.previewUrl}" width="100%" height="400px" style="border: 1px solid #ddd; border-radius: 8px;"></iframe>` :
                    '<div class="preview-error">Erro ao carregar preview</div>'
                }
            </div>
        `;
        
        if (employee.selected) {
            previewCard.classList.add('selected');
        }
        
        employeesPreview.appendChild(previewCard);
    });
    
    showSection('previewSection');
    updateGenerateButton();
}

function toggleEmployee(index) {
    extractedEmployees[index].selected = !extractedEmployees[index].selected;
    
    // Update visual state without rebuilding everything
    const previewCard = document.querySelector(`#employee-${index}`).closest('.employee-preview-card');
    if (extractedEmployees[index].selected) {
        previewCard.classList.add('selected');
    } else {
        previewCard.classList.remove('selected');
    }
    
    updateGenerateButton();
}

function updateEmployeeName(index, newName) {
    if (newName.trim()) {
        extractedEmployees[index].name = newName.trim().toUpperCase();
        // Just update the display name without rebuilding everything
        const nameInput = document.querySelector(`#employee-${index}`).closest('.employee-info').querySelector('.edit-name-inline');
        nameInput.value = extractedEmployees[index].name;
    }
}

function updateGenerateButton() {
    const generateBtn = document.getElementById('generateBtn');
    const selectedCount = extractedEmployees.filter(emp => emp.selected).length;
    generateBtn.textContent = `Gerar ${selectedCount} PDFs Separados`;
    generateBtn.disabled = selectedCount === 0;
}

async function generatePDFs() {
    try {
        showSection('processingSection');
        
        const results = [];
        const selectedEmployees = extractedEmployees.filter(emp => emp.selected);
        
        for (const employee of selectedEmployees) {
            try {
                // Read fresh data from the original file
                const pdfArrayBuffer = await currentPDFFile.arrayBuffer();
                const pdfData = new Uint8Array(pdfArrayBuffer);
                
                // Create a new PDF for this employee
                const pdfDoc = await PDFLib.PDFDocument.load(pdfData);
                const newPdf = await PDFLib.PDFDocument.create();
                
                // Copy pages for this employee
                const pageIndices = employee.pages.map(pageNum => pageNum - 1); // Convert to 0-based
                const copiedPages = await newPdf.copyPages(pdfDoc, pageIndices);
                
                copiedPages.forEach(page => {
                    newPdf.addPage(page);
                });
                
                // Generate PDF bytes
                const pdfBytes = await newPdf.save();
                
                // Create download link
                const blob = new Blob([pdfBytes], { type: 'application/pdf' });
                const url = URL.createObjectURL(blob);
                
                results.push({
                    name: employee.name,
                    url: url,
                    size: formatFileSize(pdfBytes.length),
                    pages: employee.pages.length
                });
                
            } catch (error) {
                console.error(`Erro ao gerar PDF para ${employee.name}:`, error);
                showError(`Erro ao gerar PDF para ${employee.name}`);
            }
        }
        
        displayResults(results);
        
    } catch (error) {
        console.error('Erro ao gerar PDFs:', error);
        showError('Erro ao gerar os PDFs. Tente novamente.');
        showSection('previewSection');
    }
}

function displayResults(results) {
    const downloadList = document.getElementById('downloadList');
    downloadList.innerHTML = '';
    
    results.forEach(result => {
        const item = document.createElement('div');
        item.className = 'download-item';
        item.innerHTML = `
            <div class="icon">📄</div>
            <div class="info">
                <div class="name">${result.name}</div>
                <div class="size">${result.size} • ${result.pages} página(s)</div>
            </div>
            <a href="${result.url}" download="${result.name}_holerite.pdf" class="btn-primary">
                Baixar PDF
            </a>
        `;
        downloadList.appendChild(item);
    });
    
    showSection('resultsSection');
    showSuccess(`${results.length} PDFs gerados com sucesso!`);
}

function showSection(sectionId) {
    const sections = ['uploadSection', 'processingSection', 'previewSection', 'resultsSection'];
    sections.forEach(id => {
        const section = document.getElementById(id);
        if (section) {
            section.style.display = id === sectionId ? 'block' : 'none';
            if (id === sectionId) {
                section.classList.add('fade-in');
            }
        }
    });
}

function resetApp() {
    currentPDFFile = null;
    currentPDF = null;
    extractedEmployees = [];
    processedPages = [];
    document.getElementById('fileInput').value = '';
    hideMessages();
    showSection('uploadSection');
}

function showError(message) {
    hideMessages();
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error';
    errorDiv.textContent = message;
    document.querySelector('main').insertBefore(errorDiv, document.querySelector('main').firstChild);
    
    setTimeout(() => {
        if (errorDiv.parentNode) {
            errorDiv.parentNode.removeChild(errorDiv);
        }
    }, 5000);
}

function showSuccess(message) {
    hideMessages();
    const successDiv = document.createElement('div');
    successDiv.className = 'success';
    successDiv.textContent = message;
    document.querySelector('main').insertBefore(successDiv, document.querySelector('main').firstChild);
    
    setTimeout(() => {
        if (successDiv.parentNode) {
            successDiv.parentNode.removeChild(successDiv);
        }
    }, 5000);
}

function hideMessages() {
    const messages = document.querySelectorAll('.error, .success');
    messages.forEach(msg => {
        if (msg.parentNode) {
            msg.parentNode.removeChild(msg);
        }
    });
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

async function previewEmployee(index) {
    const employee = extractedEmployees[index];
    
    try {
        // Create a modal for preview
        const modal = document.createElement('div');
        modal.className = 'preview-modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Preview - ${employee.name}</h3>
                    <button class="close-btn" onclick="closePreview()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="preview-container" id="previewContainer">
                        <div class="loading">Carregando preview...</div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        // Generate preview PDF
        const pdfArrayBuffer = await currentPDFFile.arrayBuffer();
        const pdfData = new Uint8Array(pdfArrayBuffer);
        const pdfDoc = await PDFLib.PDFDocument.load(pdfData);
        const newPdf = await PDFLib.PDFDocument.create();
        
        // Copy pages for this employee
        const pageIndices = employee.pages.map(pageNum => pageNum - 1);
        const copiedPages = await newPdf.copyPages(pdfDoc, pageIndices);
        
        copiedPages.forEach(page => {
            newPdf.addPage(page);
        });
        
        // Generate PDF bytes
        const pdfBytes = await newPdf.save();
        const blob = new Blob([pdfBytes], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        
        // Create iframe to show PDF
        const previewContainer = document.getElementById('previewContainer');
        previewContainer.innerHTML = `
            <iframe src="${url}" width="100%" height="500px" style="border: none;"></iframe>
            <div class="preview-info">
                <p><strong>Funcionário:</strong> ${employee.name}</p>
                <p><strong>Páginas:</strong> ${employee.pages.join(', ')}</p>
                <p><strong>Total de páginas:</strong> ${employee.pages.length}</p>
            </div>
        `;
        
    } catch (error) {
        console.error('Erro ao gerar preview:', error);
        showError('Erro ao gerar preview do PDF');
    }
}

function closePreview() {
    const modal = document.querySelector('.preview-modal');
    if (modal) {
        document.body.removeChild(modal);
    }
}

async function approveEmployee(index) {
    const employee = extractedEmployees[index];
    const discordId = employee.discord_id || findDiscordIdByName(employee.name);
    
    if (!discordId) {
        showError('Discord ID não encontrado para este funcionário');
        return;
    }
    
    try {
        // Show loading on button
        const approveBtn = document.querySelector(`.employee-preview-card:nth-child(${index + 1}) .btn-approve`);
        const originalText = approveBtn.innerHTML;
        approveBtn.innerHTML = '⏳ Enviando...';
        approveBtn.disabled = true;
        
        // Verificar se PDF já foi salvo
        if (!employee.savedUrl) {
            throw new Error('PDF não foi gerado ainda. Aguarde o processamento.');
        }
        
        // Prepare webhook data usando URL já salva
        const webhookData = {
            arquivo: `${employee.name.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '')}.pdf`,
            acao: 'confirmar',
            nome: employee.name,
            discord_id: discordId,
            url_pdf: employee.savedUrl
        };
        
        // Send webhook
        const response = await fetch(WEBHOOK_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(webhookData)
        });
        
        if (response.ok) {
            // Success - update UI
            approveBtn.innerHTML = '✅ Aprovado';
            approveBtn.style.backgroundColor = '#059669';
            approveBtn.classList.add('approved');
            
            showSuccess(`Holerite de ${employee.name} aprovado com sucesso!`);
            
            console.log('Webhook enviado:', webhookData);
        } else {
            throw new Error(`Erro HTTP: ${response.status}`);
        }
        
    } catch (error) {
        console.error('Erro ao enviar webhook:', error);
        showError('Erro ao aprovar holerite. Tente novamente.');
        
        // Reset button
        const approveBtn = document.querySelector(`.employee-preview-card:nth-child(${index + 1}) .btn-approve`);
        approveBtn.innerHTML = originalText;
        approveBtn.disabled = false;
    }
}

function findDiscordIdByName(employeeName) {
    console.log(`Procurando Discord ID para: "${employeeName}"`);
    
    // Normalize the search name (uppercase, clean spaces)
    const searchName = employeeName.replace(/\s+/g, ' ').trim().toUpperCase();
    
    // Try exact match first (case insensitive)
    for (const [name, id] of discordDatabase) {
        if (name === searchName) {
            console.log(`Match exato encontrado: ${employeeName} -> ${name} (ID: ${id})`);
            return id;
        }
    }
    
    // Try partial matching (checking if one name contains the other)
    for (const [name, id] of discordDatabase) {
        const dbName = name.replace(/\s+/g, ' ').trim();
        
        // Check if names match when normalized
        if (dbName.includes(searchName) || searchName.includes(dbName)) {
            console.log(`Match parcial encontrado: ${employeeName} -> ${name} (ID: ${id})`);
            return id;
        }
        
        // Try word-by-word matching (useful for different name orders)
        const searchWords = searchName.split(' ');
        const dbWords = dbName.split(' ');
        
        let matchingWords = 0;
        for (const searchWord of searchWords) {
            for (const dbWord of dbWords) {
                if (searchWord === dbWord && searchWord.length > 2) {
                    matchingWords++;
                    break;
                }
            }
        }
        
        // If most words match, consider it a match
        if (matchingWords >= Math.min(searchWords.length, dbWords.length) * 0.7) {
            console.log(`Match por palavras encontrado: ${employeeName} -> ${name} (ID: ${id}) - ${matchingWords} palavras`);
            return id;
        }
    }
    
    console.log(`Nenhum match encontrado para: ${employeeName}`);
    console.log('Nomes disponíveis no banco:', Array.from(discordDatabase.keys()));
    
    return null;
}