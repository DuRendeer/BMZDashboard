<?php
include_once 'src/includes/auth_check.php';

// Definir diretórios
$inputsDir = __DIR__ . '/Inputs/';
$holeritesDir = __DIR__ . '/Holerites/';

// Criar diretórios se não existirem
if (!is_dir($inputsDir)) {
    mkdir($inputsDir, 0755, true);
}
if (!is_dir($holeritesDir)) {
    mkdir($holeritesDir, 0755, true);
}

// URLs públicas
$baseUrl = 'https://bmzdashboard.shop/Extrato/';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Separador de Holerites</title>
    <link rel="stylesheet" href="src/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <div class="header-top">
                <div class="user-info">
                    <span>Usuário: <?php echo htmlspecialchars($_SESSION['username']); ?></span>
                    <a href="src/auth/logout.php" class="logout-btn">Sair</a>
                </div>
            </div>
            <img src="src/assets/White.png" alt="Logo" class="logo" id="logoClick">
            <h1>Separador de Holerites</h1>
            <p>Faça upload de um PDF com múltiplos holerites para separar por funcionário</p>
        </header>

        <main>
            <!-- Upload Section -->
            <section class="upload-section" id="uploadSection">
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">📄</div>
                    <p>Arraste e solte o PDF aqui ou clique para selecionar</p>
                    <input type="file" id="fileInput" accept=".pdf" hidden>
                    <button class="btn-primary" onclick="document.getElementById('fileInput').click()">
                        Selecionar PDF
                    </button>
                </div>
            </section>

            <!-- Processing Section -->
            <section class="processing-section" id="processingSection" style="display: none;">
                <div class="loading">
                    <div class="spinner"></div>
                    <p>Processando PDF...</p>
                </div>
            </section>

            <!-- Preview Section -->
            <section class="preview-section" id="previewSection" style="display: none;">
                <h2>Funcionários Encontrados</h2>
                <p class="subtitle">Visualize e verifique os holerites de cada funcionário</p>
                
                <div class="employees-preview" id="employeesPreview">
                    <!-- Employee preview cards will be inserted here -->
                </div>

                <div class="actions">
                    <button class="btn-secondary" onclick="resetApp()">Voltar</button>
                    <button class="btn-primary" onclick="generatePDFs()" id="generateBtn">
                        Gerar PDFs Separados
                    </button>
                </div>
            </section>

            <!-- Results Section -->
            <section class="results-section" id="resultsSection" style="display: none;">
                <h2>PDFs Gerados</h2>
                <p class="subtitle">Clique nos links abaixo para baixar os arquivos</p>
                
                <div class="download-list" id="downloadList">
                    <!-- Download links will be inserted here -->
                </div>

                <div class="actions">
                    <button class="btn-primary" onclick="resetApp()">Processar Novo PDF</button>
                </div>
            </section>
        </main>

        <footer>
            <p>&copy; 2025 Separador de Holerites</p>
        </footer>
    </div>

    <!-- Modal de Limpar Cache -->
    <div id="clearCacheModal" class="cache-modal" style="display: none;">
        <div class="cache-modal-overlay"></div>
        <div class="cache-card">
            <h3>Limpar Cache</h3>
            <p>Deseja apagar os documentos salvos?</p>
            <div class="cache-buttons">
                <button onclick="clearCache()" class="cache-btn-confirm">Sim</button>
                <button onclick="closeModal()" class="cache-btn-cancel">Cancelar</button>
            </div>
        </div>
    </div>

    <style>
        .cache-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .cache-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(2px);
        }

        .cache-card {
            position: relative;
            width: 190px;
            height: 254px;
            border-radius: 50px;
            background: #e0e0e0;
            box-shadow: 20px 20px 60px #bebebe, -20px -20px 60px #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 20px;
            padding: 20px;
            z-index: 1001;
        }

        .cache-card h3 {
            color: #333;
            text-align: center;
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }

        .cache-card p {
            color: #666;
            text-align: center;
            font-size: 13px;
            margin: 0;
            line-height: 1.3;
        }

        .cache-buttons {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .cache-btn-confirm, .cache-btn-cancel {
            padding: 10px 16px;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s ease;
            box-shadow: 4px 4px 8px #bebebe, -4px -4px 8px #ffffff;
        }

        .cache-btn-confirm {
            background: #dc3545;
            color: white;
        }

        .cache-btn-confirm:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .cache-btn-cancel {
            background: #6c757d;
            color: white;
        }

        .cache-btn-cancel:hover {
            background: #545b62;
            transform: translateY(-2px);
        }

        .header-top {
            position: absolute;
            top: 15px;
            right: 20px;
            z-index: 100;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            color: #666;
        }

        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 12px;
            font-weight: bold;
            transition: background 0.3s ease;
        }

        .logout-btn:hover {
            background: #c82333;
            text-decoration: none;
            color: white;
        }
    </style>

    <script src="src/js/script.js"></script>
    <script>
        let clickCount = 0;
        let clickTimer = null;

        document.getElementById('logoClick').addEventListener('click', function() {
            clickCount++;
            
            if (clickCount === 1) {
                clickTimer = setTimeout(() => {
                    clickCount = 0;
                }, 3000);
            }
            
            if (clickCount === 10) {
                clearTimeout(clickTimer);
                clickCount = 0;
                document.getElementById('clearCacheModal').style.display = 'flex';
            }
        });

        function closeModal() {
            document.getElementById('clearCacheModal').style.display = 'none';
        }

        function clearCache() {
            fetch('src/admin/clear_cache.php', {
                method: 'POST'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Cache limpo com sucesso!');
                } else {
                    alert('Erro ao limpar cache: ' + data.message);
                }
                closeModal();
            })
            .catch(error => {
                alert('Erro ao limpar cache');
                closeModal();
            });
        }
    </script>
</body>
</html>