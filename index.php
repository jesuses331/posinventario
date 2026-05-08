<?php
require_once __DIR__ . '/models/Auth.php';

if (Auth::isLoggedIn()) {
    header("Location: views/dashboard.php");
    exit();
} else {
    header("Location: views/login.php");
    exit();
}
