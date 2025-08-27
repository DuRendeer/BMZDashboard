<?php
// ========== CONFIGURAÇÕES DE EMAIL ==========
// Configure aqui os emails que podem ser usados como remetente
$emails_remetentes = [
    'gmail1' => [
        'email' => 'bmzeduardoochodolak@gmail.com',
        'senha' => 'qmfi vbnt gyry hgpu',
        'nome' => 'Eduardo Sochodolak'
    ],
    'gmail2' => [
        'email' => 'seuemail2@gmail.com', 
        'senha' => 'suasenhaapp2',
        'nome' => 'Nome Remetente 2'
    ],
    'outlook' => [
        'email' => 'seuemail@outlook.com',
        'senha' => 'suasenha',
        'nome' => 'Nome Remetente 3'
    ]
];

// ========== LISTAS DE DESTINATÁRIOS ==========
$listas_emails = [
    'todos' => [
        'bmzjanayanafreisleben@gmail.com',
        'carlapachecobmz@gmail.com', 
        'guilherme_zaias@hotmail.com',
        'bmzjamilescheidt@gmail.com',
        'bmzcamilenunes@gmail.com',
        'bmzlucassilva@gmail.com',
        'bmzgabrielyholodivski@gmail.com',
        'bmzbrunalukaski@gmail.com',
        'bmzgilianeplodowski@gmail.com',
        'bmzevairoliveira@gmail.com',
        'bmzbiancastaxiv@gmail.com',
        'bmzjosearaujo@gmail.com',
        'bmzjessicariffel@gmail.com',
        'bmzmargaretedorak@gmail.com',
        'HenriqueLeite37@gmail.com',
        'bmzelaineoliveira@gmail.com',
        'bmzthamiresandrade@gmail.com',
        'andrielebmzadvogados@gmail.com',
        'bmzmilleneleal@gmail.com',
        'Leonardobelink@gmail.com',
        'bmzericaokarensakie@gmail.com',
        'guilhermezaiats@gmail.com',
        'bmzrodrigogarbachevski@gmail.com',
        'bmzdeliaochoa@gmail.com',
        'bmzjoaoguilherme@gmail.com',
        'bmzjaquelinepapirniak@gmail.com',
        'bmzkelitaschultz@gmail.com',
        'bmzmarialeticia@gmail.com',
        'diarle_medeiros@hotmail.com',
        'bmzamandakloster@gmail.com',
        'bmzsthephanienascimento@gmail.com',
        'bmzmariarita@gmail.com',
        'bmzkarensochodolak@gmail.com',
        'bmzgessicatrzesniovski@gmail.com',
        'bmzhevilinvitoria@gmail.com',
        'bmzeduardosochodolak@gmail.com',
        'bmzliedsonwinharski@gmail.com',
        'bmzgiselesaplak@gmail.com',
        'bmzalexanderscosta@gmail.com',
        'bmzlucaseduardo@gmail.com',
        'bmzkarenalessandra@gmail.com',
        'bmzmaisabail@gmail.com',
        'bmzhilaryerddmann@gmail.com',
        'bmzadrianokolistki@gmail.com',
        'bmzanapaulamelnik@gmail.com',
        'bmzhenriquegerei@gmail.com',
        'bmzalexsandercosta@gmail.com',
        'gabrielle@imapactapro.net',
        'bmzrafaelcodolo@gmail.com',
        'bmzgabrielakorchak@gmail.com'
    ],
    
    'testes' => [
        'bmzleonardomarconato@gmail.com',
        'bmzpinicial@gmail.com',
        'carlapachecobmz@gmail.com',

    ]
];

// ========== PROCESSAMENTO ==========
$resultado = '';
if ($_POST) {
    $titulo = $_POST['titulo'] ?? '';
    $mensagem = $_POST['mensagem'] ?? '';
    $remetente_key = $_POST['remetente'] ?? '';
    $lista_escolhida = $_POST['lista'] ?? 'todos';
    $arquivos = $_FILES['arquivos'] ?? [];
    
    if (!empty($titulo) && !empty($mensagem) && !empty($remetente_key)) {
        if (isset($emails_remetentes[$remetente_key]) && isset($listas_emails[$lista_escolhida])) {
            $contador = enviarEmails($titulo, $mensagem, $arquivos, $remetente_key, $lista_escolhida);
            $total = count($listas_emails[$lista_escolhida]);
            $lista_nome = $lista_escolhida == 'todos' ? 'Todos' : 'Testes';
            $resultado = "✅ Emails enviados! $contador de $total para a lista '$lista_nome'";
        } else {
            $resultado = "❌ Erro: Remetente ou lista inválida.";
        }
    } else {
        $resultado = "❌ Por favor, preencha todos os campos obrigatórios.";
    }
}

function enviarEmails($titulo, $mensagem, $arquivos, $remetente_key, $lista_escolhida) {
    global $emails_remetentes, $listas_emails;
    
    $remetente = $emails_remetentes[$remetente_key];
    $lista_emails = $listas_emails[$lista_escolhida];
    
    $contador = 0;
    
    // Configuração SMTP para Gmail/Outlook
    ini_set('SMTP', $remetente_key == 'outlook' ? 'smtp-mail.outlook.com' : 'smtp.gmail.com');
    ini_set('smtp_port', $remetente_key == 'outlook' ? '587' : '587');
    
    // Headers
    $headers = "From: {$remetente['nome']} <{$remetente['email']}>\r\n";
    $headers .= "Reply-To: {$remetente['email']}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    
    // Se tem anexos, usar multipart
    if (!empty($arquivos['name'][0])) {
        $boundary = md5(time());
        $headers .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
        
        $corpo = "--$boundary\r\n";
        $corpo .= "Content-Type: text/html; charset=UTF-8\r\n";
        $corpo .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $corpo .= criarCorpoHTML($titulo, $mensagem, $remetente);
        $corpo .= "\r\n";
        
        // Adicionar anexos
        for ($i = 0; $i < count($arquivos['name']); $i++) {
            if ($arquivos['error'][$i] == 0) {
                $arquivo_nome = $arquivos['name'][$i];
                $arquivo_temp = $arquivos['tmp_name'][$i];
                $arquivo_conteudo = chunk_split(base64_encode(file_get_contents($arquivo_temp)));
                $arquivo_tipo = mime_content_type($arquivo_temp);
                
                $corpo .= "--$boundary\r\n";
                $corpo .= "Content-Type: $arquivo_tipo; name=\"$arquivo_nome\"\r\n";
                $corpo .= "Content-Transfer-Encoding: base64\r\n";
                $corpo .= "Content-Disposition: attachment; filename=\"$arquivo_nome\"\r\n\r\n";
                $corpo .= $arquivo_conteudo . "\r\n";
            }
        }
        $corpo .= "--$boundary--\r\n";
    } else {
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $corpo = criarCorpoHTML($titulo, $mensagem, $remetente);
    }
    
    // Enviar para cada email
    foreach ($lista_emails as $email) {
        $email = trim($email);
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $enviado = mail($email, $titulo, $corpo, $headers);
            if ($enviado) {
                $contador++;
            }
            usleep(300000); // 0.3 segundos entre envios
        }
    }
    
    return $contador;
}

function criarCorpoHTML($titulo, $mensagem, $remetente) {
    return "
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { 
                font-family: 'Segoe UI', Arial, sans-serif; 
                line-height: 1.6; 
                color: #333; 
                margin: 0; 
                padding: 0; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: #ffffff;
            }
            .header { 
                background: linear-gradient(135deg, #06294eff, #122b46ff); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
                border-radius: 8px 8px 0 0;
            }
            .header h1 { 
                margin: 0; 
                font-size: 24px; 
                font-weight: 600;
            }
            .content { 
                padding: 30px 20px; 
                background: #ffffff;
            }
            .message {
                font-size: 16px;
                line-height: 1.8;
                margin-bottom: 20px;
            }
            .footer { 
                background: #f8f9fa; 
                padding: 20px; 
                text-align: center; 
                font-size: 12px; 
                color: #6c757d; 
                border-top: 1px solid #dee2e6;
                border-radius: 0 0 8px 8px;
            }
            .signature {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid #eee;
                font-size: 14px;
                color: #495057;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>$titulo</h1>
            </div>
            <div class='content'>
                <div class='message'>
                    " . nl2br(htmlspecialchars($mensagem)) . "
                </div>
                <div class='signature'>
                    <strong>{$remetente['nome']}</strong><br>
                    <a href='mailto:{$remetente['email']}' style='color: #007bff; text-decoration: none;'>{$remetente['email']}</a>
                </div>
            </div>
            <div class='footer'>
                <p>📧 Email enviado em " . date('d/m/Y \à\s H:i') . "</p>
                <p>Sistema de Envio Automático</p>
            </div>
        </div>
    </body>
    </html>";
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>📧 Sistema de Email BMZ</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffffffff 0%, #f9f7fcff 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #0c2743ff, #0e437cff);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.2em;
            margin-bottom: 10px;
        }
        
        .header p {
            opacity: 0.9;
            font-size: 1.1em;
        }
        
        .content {
            padding: 30px;
        }
        
        .config-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .config-section h3 {
            color: #856404;
            margin-bottom: 15px;
        }
        
        .config-section code {
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }
        
        .stat-box h4 {
            font-size: 2em;
            margin-bottom: 5px;
        }
        
        .stat-box p {
            opacity: 0.9;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        .form-group select,
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0,123,255,0.1);
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .file-input {
            position: relative;
        }
        
        .file-input input[type="file"] {
            opacity: 0;
            position: absolute;
            z-index: -1;
        }
        
        .file-label {
            display: inline-block;
            padding: 12px 20px;
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
            text-align: center;
        }
        
        .file-label:hover {
            background: #e9ecef;
            border-color: #007bff;
        }
        
        .btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            width: 100%;
            transition: transform 0.2s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        .resultado {
            margin-top: 20px;
            padding: 20px;
            border-radius: 8px;
            font-weight: 500;
            text-align: center;
            animation: slideIn 0.5s ease;
            font-size: 16px;
        }
        
        .resultado.sucesso {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .resultado.erro {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .small-text {
            font-size: 0.85em;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .lista-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-top: 8px;
            max-height: 100px;
            overflow-y: auto;
            font-size: 0.85em;
            color: #495057;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 Sistema de Email BMZ</h1>
            <p>Envio profissional para equipes</p>
        </div>
        
        <div class="content">
            <div class="config-section">
                <h3>⚙️ Configuração Necessária</h3>
                <p><strong>Configure antes de usar:</strong></p>
                <ul style="margin-left: 20px; margin-top: 10px;">
                    <li>Edite <code>$emails_remetentes</code> (linhas 4-18) com seus dados de email</li>
                    <li>Para Gmail: use senhas de app em <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a></li>
                    <li>Para Outlook: use sua senha normal</li>
                </ul>
            </div>
            
            <div class="stats-grid">
                <div class="stat-box">
                    <h4><?= count($listas_emails['todos']) ?></h4>
                    <p>📋 Lista Todos</p>
                </div>
                <div class="stat-box">
                    <h4><?= count($listas_emails['testes']) ?></h4>
                    <p>🧪 Lista Testes</p>
                </div>
                <div class="stat-box">
                    <h4><?= count($emails_remetentes) ?></h4>
                    <p>📤 Remetentes</p>
                </div>
            </div>
            
            <?php if ($resultado): ?>
                <div class="resultado <?= strpos($resultado, '✅') !== false ? 'sucesso' : 'erro' ?>">
                    <?= $resultado ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="remetente">📤 Enviar de:</label>
                        <select id="remetente" name="remetente" required>
                            <option value="">Escolha o remetente...</option>
                            <?php foreach ($emails_remetentes as $key => $remetente): ?>
                                <option value="<?= $key ?>">
                                    <?= $remetente['nome'] ?> (<?= $remetente['email'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="lista">📋 Enviar para:</label>
                        <select id="lista" name="lista" required onchange="mostrarLista()">
                            <option value="todos">📋 Lista Todos (<?= count($listas_emails['todos']) ?> emails)</option>
                            <option value="testes">🧪 Lista Testes (<?= count($listas_emails['testes']) ?> emails)</option>
                        </select>
                        <div id="preview-lista" class="lista-preview"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="titulo">📝 Assunto do Email:</label>
                    <input type="text" id="titulo" name="titulo" required 
                           placeholder="Ex: Reunião importante - 15/08 às 14h">
                </div>
                
                <div class="form-group">
                    <label for="mensagem">💬 Mensagem:</label>
                    <textarea id="mensagem" name="mensagem" required 
                              placeholder="Olá pessoal,&#10;&#10;Escreva aqui sua mensagem...&#10;&#10;Atenciosamente,&#10;Sua equipe"></textarea>
                </div>
                
                <div class="form-group">
                    <label>📎 Anexar Arquivos (opcional):</label>
                    <div class="file-input">
                        <input type="file" id="arquivos" name="arquivos[]" multiple 
                               accept="image/*,.pdf,.doc,.docx,.txt,.xlsx,.ppt,.pptx,.zip">
                        <label for="arquivos" class="file-label">
                            🗂️ Clique para escolher arquivos<br>
                            <span class="small-text">Documentos, fotos, PDFs... (múltiplos permitidos)</span>
                        </label>
                    </div>
                </div>
                
                <button type="submit" class="btn">
                    🚀 Enviar Emails Agora
                </button>
            </form>
        </div>
    </div>

    <script>
        const listas = <?= json_encode($listas_emails) ?>;
        
        function mostrarLista() {
            const select = document.getElementById('lista');
            const preview = document.getElementById('preview-lista');
            const lista = listas[select.value];
            
            if (lista && lista.length > 0) {
                const primeiros = lista.slice(0, 5).join(', ');
                const resto = lista.length > 5 ? `... e mais ${lista.length - 5} emails` : '';
                preview.innerHTML = primeiros + resto;
            }
        }
        
        // Mostrar arquivos selecionados
        document.getElementById('arquivos').addEventListener('change', function(e) {
            const label = document.querySelector('.file-label');
            const files = e.target.files;
            
            if (files.length > 0) {
                let fileNames = Array.from(files).map(file => file.name).join(', ');
                if (fileNames.length > 60) {
                    fileNames = fileNames.substring(0, 60) + '...';
                }
                label.innerHTML = `📁 ${files.length} arquivo(s)<br><span class="small-text">${fileNames}</span>`;
            } else {
                label.innerHTML = `🗂️ Clique para escolher arquivos<br><span class="small-text">Documentos, fotos, PDFs... (múltiplos permitidos)</span>`;
            }
        });
        
        // Mostrar lista inicial
        mostrarLista();
    </script>
</body>
</html>