<?php
header('Content-Type: application/json');

$response = [];

// Testar se pasta existe
$musicDir = '../Musicas Primeiro Atendimento';
$response['pasta_existe'] = file_exists($musicDir) && is_dir($musicDir);
$response['caminho_pasta'] = realpath($musicDir);

// Listar arquivos na pasta
if ($response['pasta_existe']) {
    $files = scandir($musicDir);
    $response['arquivos'] = array_filter($files, function($file) use ($musicDir) {
        return !in_array($file, ['.', '..']) && is_file($musicDir . '/' . $file);
    });
    $response['total_arquivos'] = count($response['arquivos']);
} else {
    $response['arquivos'] = [];
    $response['total_arquivos'] = 0;
}

// Testar arquivo específico
$testFile = $musicDir . '/Alas.mp3';
$response['alas_existe'] = file_exists($testFile);
$response['alas_tamanho'] = file_exists($testFile) ? filesize($testFile) : 0;

// URLs de teste
$response['url_teste'] = 'Musicas Primeiro Atendimento/Alas.mp3';
$response['url_absoluta'] = $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . '/../Musicas Primeiro Atendimento/Alas.mp3';

echo json_encode($response, JSON_PRETTY_PRINT);
?>