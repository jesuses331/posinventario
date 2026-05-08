<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../models/Database.php';
require_once __DIR__ . '/../../models/Auth.php';
require_once __DIR__ . '/../../models/Usuario.php';

Auth::checkAccess('admin');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function json_out_users(int $code, array $payload): void {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function require_csrf_users(): void {
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    if (!isset($_SESSION['csrf_token']) || !is_string($token) || !hash_equals($_SESSION['csrf_token'], $token)) {
        json_out_users(403, ['ok' => false, 'message' => 'CSRF inválido.']);
    }
}

try {
    $db = (new Database())->getConnection();
    $usuario = new Usuario($db);
} catch (Throwable $e) {
    json_out_users(500, ['ok' => false, 'message' => 'Error de conexión.']);
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if ($action === 'list') {
    $rows = $usuario->listAll();
    json_out_users(200, ['ok' => true, 'data' => $rows]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) json_out_users(400, ['ok' => false, 'message' => 'ID inválido.']);
    $row = $usuario->findById($id);
    if (!$row) json_out_users(404, ['ok' => false, 'message' => 'Usuario no encontrado.']);
    json_out_users(200, ['ok' => true, 'data' => $row]);
}

if ($action === 'save') {
    require_csrf_users();

    $id = (int)($_POST['id'] ?? 0);
    $nombre = trim((string)($_POST['nombre'] ?? ''));
    $username = trim((string)($_POST['usuario'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $rol = (string)($_POST['rol'] ?? 'cajero');
    $estado = (int)($_POST['estado'] ?? 1);
    $password = (string)($_POST['password'] ?? '');

    if ($nombre === '' || $username === '' || $email === '') {
        json_out_users(422, ['ok' => false, 'message' => 'Nombre, usuario y email son obligatorios.']);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out_users(422, ['ok' => false, 'message' => 'Email inválido.']);
    }
    if (!in_array($rol, ['admin', 'cajero'], true)) {
        json_out_users(422, ['ok' => false, 'message' => 'Rol inválido.']);
    }
    $estado = $estado === 0 ? 0 : 1;

    if ($usuario->usuarioExists($username, $id > 0 ? $id : null)) {
        json_out_users(409, ['ok' => false, 'message' => 'El nombre de usuario ya está registrado.']);
    }

    if ($usuario->emailExists($email, $id > 0 ? $id : null)) {
        json_out_users(409, ['ok' => false, 'message' => 'El email ya está registrado.']);
    }

    $payload = [
        'nombre' => $nombre,
        'usuario' => $username,
        'email' => $email,
        'rol' => $rol,
        'estado' => $estado,
        'password_hash' => '',
    ];
    if ($password !== '') {
        $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
    }

    try {
        if ($id > 0) {
            $usuario->update($id, $payload);
            json_out_users(200, ['ok' => true, 'message' => 'Usuario actualizado.']);
        }
        $newId = $usuario->create($payload);
        json_out_users(201, ['ok' => true, 'message' => 'Usuario creado.', 'id' => $newId]);
    } catch (Throwable $e) {
        json_out_users(500, ['ok' => false, 'message' => 'Error guardando usuario.']);
    }
}

json_out_users(400, ['ok' => false, 'message' => 'Acción inválida.']);

