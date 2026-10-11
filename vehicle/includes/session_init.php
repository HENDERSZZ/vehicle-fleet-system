<?php
// Keeps login sessions 
if (session_status() === PHP_SESSION_NONE) {
    $sessionPath = __DIR__ . '/../storage/sessions';
    if (!is_dir($sessionPath)) {
        @mkdir($sessionPath, 0700, true);
    }
    if (is_dir($sessionPath) && is_writable($sessionPath)) {
        session_save_path($sessionPath);
    }
    ini_set('session.gc_maxlifetime', 3600); // keep session data for 1 hour of inactivity
    session_set_cookie_params(3600);
    session_start();
}