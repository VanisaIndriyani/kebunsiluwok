<?php
$__sessDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
if (!is_dir($__sessDir)) { mkdir($__sessDir, 0777, true); }
ini_set('session.save_path', $__sessDir);
session_save_path($__sessDir);
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
