<?php
echo "🔍 Testando sync.php<br><br>";

echo "1. Testando resposta do sync.php?action=sync<br>";
$url = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/sync.php?action=sync';
echo "URL: $url<br><br>";

$context = stream_context_create([
    'http' => [
        'timeout' => 10
    ]
]);

$response = file_get_contents($url, false, $context);

echo "2. Resposta recebida:<br>";
echo "<pre style='background:#f0f0f0;padding:10px;'>";
echo htmlspecialchars($response);
echo "</pre><br>";

echo "3. Tentando decodificar JSON:<br>";
$json = json_decode($response, true);

if ($json) {
    echo "✅ JSON válido!<br>";
    print_r($json);
} else {
    echo "❌ JSON inválido! Erro: " . json_last_error_msg() . "<br>";
    echo "Código do erro: " . json_last_error() . "<br>";
}

echo "<br>4. Headers da resposta:<br>";
echo "<pre>";
print_r($http_response_header ?? 'Headers não disponíveis');
echo "</pre>";
?>