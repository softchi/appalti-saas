<?php
define('APP_INIT', true);
require_once __DIR__ . '/../php/bootstrap.php';
Auth::logout();
if (isAjax()) { jsonSuccess('Logout effettuato'); }
redirect(APP_URL . '/login.php');
