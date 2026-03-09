<?php
/**
 * Appalti SaaS — Entry Point
 * Reindirizza al login se non autenticato, altrimenti alla dashboard.
 */
define('APP_INIT', true);
require_once __DIR__ . '/php/bootstrap.php';

if (Auth::check()) {
    header('Location: ' . APP_URL . '/pages/dashboard.php');
} else {
    header('Location: ' . APP_URL . '/login.php');
}
exit;
