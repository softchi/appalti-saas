<?php
/**
 * API: Autenticazione
 * POST /api/login.php
 */
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';

header('Content-Type: application/json; charset=UTF-8');

if (!isMethod('POST')) {
    jsonError('Metodo non consentito', 405);
}

// Supporta sia form-encoded che JSON body
$body = !empty($_POST) ? $_POST : getJsonBody();

$email      = sanitizeEmail($body['email'] ?? '');
$password   = $body['password'] ?? '';
$rememberMe = (bool)($body['remember_me'] ?? false);

// Validazione base
if (!$email) {
    jsonError('Email non valida', 422, ['email' => 'Email non valida o mancante']);
}
if (empty($password)) {
    jsonError('Password richiesta', 422, ['password' => 'Password richiesta']);
}

// Esegui login
$result = Auth::login($email, $password, $rememberMe);

if (!$result['success']) {
    jsonError($result['message'], 401);
}

jsonSuccess('Login effettuato con successo', [
    'user'       => $result['user'],
    'csrf_token' => Auth::csrfToken(),
    'redirect'   => urldecode($_GET['redirect'] ?? '') ?: APP_URL . '/pages/dashboard.php',
]);
