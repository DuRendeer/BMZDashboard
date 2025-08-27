<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔍 Debug Dashboard</h1>";

try {
    echo "✅ PHP funcionando<br>";
    
    echo "📁 Testando includes...<br>";
    if (file_exists('backend/DatabaseManager.php')) {
        echo "✅ DatabaseManager.php encontrado<br>";
        require_once 'backend/DatabaseManager.php';
    } else {
        echo "❌ DatabaseManager.php não encontrado<br>";
    }
    
    if (file_exists('backend/SyncService.php')) {
        echo "✅ SyncService.php encontrado<br>";
        require_once 'backend/SyncService.php';
    } else {
        echo "❌ SyncService.php não encontrado<br>";
    }
    
    if (file_exists('backend/config.php')) {
        echo "✅ config.php encontrado<br>";
        require_once 'backend/config.php';
    } else {
        echo "❌ config.php não encontrado<br>";
    }
    
    echo "🗄️ Testando conexão com banco...<br>";
    $db = new DatabaseManager();
    echo "✅ Conexão com banco OK<br>";
    
    echo "👥 Testando consulta usuários...<br>";
    $users = $db->getUsers();
    echo "✅ Usuários encontrados: " . count($users) . "<br>";
    
    echo "📊 Testando stats...<br>";
    $stats = $db->getDashboardStats();
    echo "✅ Stats OK<br>";
    echo "<pre>" . print_r($stats, true) . "</pre>";
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "<br>";
    echo "📍 Arquivo: " . $e->getFile() . " linha " . $e->getLine() . "<br>";
    echo "🔍 Trace:<br><pre>" . $e->getTraceAsString() . "</pre>";
}
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: #f5f5f5; 
}
h1 { color: #333; }
</style>