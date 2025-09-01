<?php
// Configurações do sistema - NÃO COMMITAR ESTE ARQUIVO
function getCredentials() {
    // Hash das credenciais para segurança
    $credentials = [
        'email' => hash('sha256', ''),
        'password' => hash('sha256', '')
    ];
    
    return $credentials;
}

function validateLogin($email, $password) {
    $validCredentials = getCredentials();
    
    $emailHash = hash('sha256', trim($email));
    $passwordHash = hash('sha256', $password);
    
    return ($emailHash === $validCredentials['email'] && 
            $passwordHash === $validCredentials['password']);
}
?>