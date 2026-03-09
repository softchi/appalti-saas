<?php
/**
 * Script TEMPORANEO per reset password admin
 * DA ELIMINARE subito dopo l'uso!
 * Accedi a: https://softchi.altervista.org/1_GestPM/v5/appalti-saas-main/reset_pw.php
 */
define('APP_INIT', true);
require_once __DIR__ . '/php/bootstrap.php';

// Imposta qui la nuova password
$nuovaPassword = 'Admin2024!';
$email         = 'admin@appalti.local';

$hash = password_hash($nuovaPassword, PASSWORD_BCRYPT, ['cost' => PASSWORD_COST]);

$rows = Database::update('pm_utenti', ['password_hash' => $hash], ['email' => $email]);

header('Content-Type: text/plain');
if ($rows > 0) {
    echo "OK: password aggiornata per {$email}\n";
    echo "Nuova password: {$nuovaPassword}\n";
    echo "ELIMINA QUESTO FILE ORA!";
} else {
    echo "ERRORE: utente {$email} non trovato o password invariata.";
}
