<?php
// Entry point for the Finance Tracker
session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit();
}

require_once '../src/dashboard.php';
?>