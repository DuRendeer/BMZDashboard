<?php
include_once '../includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autorizado - faça login primeiro']);
    exit();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Definir diretórios
$inputsDir = dirname(dirname(__DIR__)) . '/Inputs/';
$holeritesDir = dirname(dirname(__DIR__)) . '/Holerites/';
$baseUrl = 'https://bmzdashboard.shop/Extrato/';

// Criar diretórios se não existirem
if (!is_dir($inputsDir)) {
    mkdir($inputsDir, 0755, true);
}
if (!is_dir($holeritesDir)) {
    mkdir($holeritesDir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload-input':
            handleInputUpload();
            break;
            
        case 'save-holerite':
            handleHoleriteSave();
            break;
            
        default:
            throw new Exception('Ação não encontrada');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}

function handleInputUpload() {
    global $inputsDir, $baseUrl;
    
    if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Nenhum arquivo PDF foi enviado');
    }

    $uploadedFile = $_FILES['pdf'];
    
    // Validar se é PDF
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $uploadedFile['tmp_name']);
    finfo_close($finfo);
    
    if ($mimeType !== 'application/pdf') {
        throw new Exception('Arquivo deve ser um PDF');
    }
    
    // Criar nome do arquivo
    $timestamp = date('Y-m-d_H-i-s');
    $originalName = pathinfo($uploadedFile['name'], PATHINFO_FILENAME);
    $fileName = "input_{$timestamp}_{$originalName}.pdf";
    
    $destinationPath = $inputsDir . $fileName;
    
    if (!move_uploaded_file($uploadedFile['tmp_name'], $destinationPath)) {
        throw new Exception('Erro ao salvar arquivo');
    }
    
    echo json_encode([
        'success' => true,
        'filename' => $fileName,
        'url' => $baseUrl . "Inputs/{$fileName}",
        'size' => filesize($destinationPath)
    ]);
}

function handleHoleriteSave() {
    global $holeritesDir, $baseUrl;
    
    // Ler dados JSON
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    $requiredFields = ['nome', 'pdfData'];
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Campo obrigatório: {$field}");
        }
    }
    
    // Limpar nome para usar como arquivo
    $cleanName = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $input['nome']);
    $cleanName = preg_replace('/\s+/', '_', $cleanName);
    $cleanName = trim($cleanName, '_');
    $cleanName = strtoupper($cleanName);
    
    $fileName = "{$cleanName}.pdf";
    $destinationPath = $holeritesDir . $fileName;
    
    // Decodificar PDF base64
    $pdfData = base64_decode($input['pdfData']);
    
    if ($pdfData === false) {
        throw new Exception('Dados PDF inválidos');
    }
    
    if (file_put_contents($destinationPath, $pdfData) === false) {
        throw new Exception('Erro ao salvar holerite');
    }
    
    // URL pública para o Make
    $publicUrl = $baseUrl . "Holerites/{$cleanName}.pdf";
    
    echo json_encode([
        'success' => true,
        'filename' => $fileName,
        'url' => $publicUrl,
        'localPath' => "Holerites/{$fileName}",
        'size' => strlen($pdfData),
        'cleanName' => $cleanName
    ]);
}
?>