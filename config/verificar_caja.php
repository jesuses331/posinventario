<?php
// Evitar re-inclusión de archivos si ya están cargados
if (!defined('DB_HOST')) {
    require_once __DIR__ . '/db.php';
}
if (!class_exists('Database')) {
    require_once __DIR__ . '/../models/Database.php';
}
if (!class_exists('Caja')) {
    require_once __DIR__ . '/../models/Caja.php';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    $loginUrl = (defined('BASE_URL') ? BASE_URL : '/') . "views/login.php";
    header("Location: $loginUrl");
    exit();
}

try {
    $database = new Database();
    $db = $database->getConnection();
    $cajaModel = new Caja($db);

    $cajaAbierta = $cajaModel->obtenerEstado($_SESSION['user_id']);

    // Obtener el nombre del archivo actual para evitar bucles de redirección
    $currentPage = basename($_SERVER['PHP_SELF']);

    // Si no hay una caja abierta y no estamos en la página de apertura, redirigir
    if (!$cajaAbierta && $currentPage !== 'apertura_caja.php') {
        $aperturaUrl = (defined('BASE_URL') ? BASE_URL : '/') . "views/modules/apertura_caja.php";
        header("Location: $aperturaUrl");
        exit();
    }

    // Si hay una caja abierta, guardar su ID en la sesión
    if ($cajaAbierta) {
        $_SESSION['id_caja'] = $cajaAbierta['id'];
    } else {
        unset($_SESSION['id_caja']);
    }

} catch (Exception $e) {
    // Si hay un error de DB, lo mostramos para depurar
    echo "Error en verificación de caja: " . $e->getMessage();
    exit();
}
