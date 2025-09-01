<?php
include_once '../includes/auth_check.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado - faça login primeiro']);
    exit();
}

function deleteFiles($dir) {
    if (!is_dir($dir)) {
        return true;
    }
    
    $files = array_diff(scandir($dir), array('.', '..'));
    $deletedCount = 0;
    $errors = [];
    
    foreach ($files as $file) {
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_file($filePath)) {
            if (unlink($filePath)) {
                $deletedCount++;
            } else {
                $errors[] = $file;
            }
        }
    }
    
    return ['deleted' => $deletedCount, 'errors' => $errors];
}

try {
    $inputsDir = dirname(dirname(__DIR__)) . '/Inputs/';
    $holeritesDir = dirname(dirname(__DIR__)) . '/Holerites/';
    
    $inputsResult = deleteFiles($inputsDir);
    $holeritesResult = deleteFiles($holeritesDir);
    
    $totalDeleted = $inputsResult['deleted'] + $holeritesResult['deleted'];
    $totalErrors = array_merge($inputsResult['errors'], $holeritesResult['errors']);
    
    if (empty($totalErrors)) {
        echo json_encode([
            'success' => true, 
            'message' => "Cache limpo com sucesso! {$totalDeleted} arquivos removidos."
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => "Alguns arquivos não puderam ser removidos: " . implode(', ', $totalErrors)
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno: ' . $e->getMessage()
    ]);
}
?>